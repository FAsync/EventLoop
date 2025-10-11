<?php

namespace Hibla\EventLoop\IOHandlers\File;

use Generator;
use Hibla\EventLoop\EventLoop;
use Hibla\EventLoop\ValueObjects\FileOperation;
use Hibla\EventLoop\ValueObjects\StreamWatcher;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final readonly class FileOperationHandler
{
    private const CHUNK_SIZE = 8192;

    /**
     * @param  array<string, mixed>  $options
     */
    public function createOperation(
        string $type,
        string $path,
        mixed $data,
        callable $callback,
        array $options = []
    ): FileOperation {
        return new FileOperation($type, $path, $data, $callback, $options);
    }

    public function executeOperation(FileOperation $operation): bool
    {
        if ($operation->isCancelled()) {
            return false;
        }

        if ($this->shouldUseStreaming($operation)) {
            $this->executeStreamingOperation($operation);
        } else {
            $this->executeOperationSync($operation);
        }

        return true;
    }

    private function shouldUseStreaming(FileOperation $operation): bool
    {
        $streamableOperations = ['read', 'write', 'copy'];

        if (! in_array($operation->getType(), $streamableOperations, true)) {
            return false;
        }

        $options = $operation->getOptions();
        $useStreaming = $options['use_streaming'] ?? false;

        return is_scalar($useStreaming) && (bool) $useStreaming;
    }

    private function handleWriteFromGenerator(FileOperation $operation): void
    {
        if ($operation->isCancelled()) {
            return;
        }

        $path = $operation->getPath();
        $generator = $operation->getData();
        $options = $operation->getOptions();

        if (! $generator instanceof Generator) {
            throw new InvalidArgumentException('Data must be a Generator for write_generator operation.');
        }

        $createDirs = $options['create_directories'] ?? false;
        if (is_scalar($createDirs) && (bool) $createDirs) {
            $dir = dirname($path);
            if (! is_dir($dir)) {
                if (! mkdir($dir, 0755, true)) {
                    $operation->executeCallback("Failed to create directory: $dir");

                    return;
                }
            }
        }

        $mode = 'wb';
        $flagsRaw = $options['flags'] ?? 0;
        if (is_numeric($flagsRaw) && (((int) $flagsRaw & FILE_APPEND) !== 0)) {
            $mode = 'ab';
        }

        $stream = @fopen($path, $mode);
        if ($stream === false) {
            $operation->executeCallback("Failed to open file for writing: $path");

            return;
        }

        stream_set_blocking($stream, false);
        stream_set_write_buffer($stream, 8192);

        $bytesWritten = 0;
        $currentChunk = '';
        $chunkOffset = 0;

        $this->scheduleGeneratorWrite(
            $operation,
            $stream,
            $generator,
            $bytesWritten,
            $currentChunk,
            $chunkOffset
        );
    }

    /**
     * @param resource $stream
     * @param-out string $currentChunk
     */
    private function scheduleGeneratorWrite(
        FileOperation $operation,
        $stream,
        Generator $generator,
        int &$bytesWritten,
        string &$currentChunk,
        int &$chunkOffset
    ): void {
        if ($operation->isCancelled()) {
            fclose($stream);

            return;
        }

        $chunksPerTick = 100;
        $chunksProcessed = 0;

        while ($chunksProcessed < $chunksPerTick) {
            if ($chunkOffset >= strlen($currentChunk)) {
                if (! $generator->valid()) {
                    fclose($stream);
                    $operation->executeCallback(null, $bytesWritten);

                    return;
                }

                $chunkValue = $generator->current();

                if (! is_string($chunkValue)) {
                    fclose($stream);
                    $operation->executeCallback('Generator must yield string chunks');

                    return;
                }

                $currentChunk = $chunkValue;
                $chunkOffset = 0;
                $generator->next();
            }

            $remainingData = substr($currentChunk, $chunkOffset);
            $written = @fwrite($stream, $remainingData);

            if ($written === false) {
                fclose($stream);
                $operation->executeCallback('Failed to write to file');

                return;
            }

            if ($written === 0) {
                $watcherId = EventLoop::getInstance()->addStreamWatcher(
                    $stream,
                    function () use ($operation, $stream, $generator, &$bytesWritten, &$currentChunk, &$chunkOffset, &$watcherId) {
                        if ($watcherId !== null) {
                            EventLoop::getInstance()->removeStreamWatcher($watcherId);
                        }
                        $this->scheduleGeneratorWrite(
                            $operation,
                            $stream,
                            $generator,
                            $bytesWritten,
                            $currentChunk,
                            $chunkOffset
                        );
                    },
                    StreamWatcher::TYPE_WRITE
                );

                return;
            }

            $bytesWritten += $written;
            $chunkOffset += $written;
            $chunksProcessed++;
        }

        EventLoop::getInstance()->nextTick(function () use ($operation, $stream, $generator, &$bytesWritten, &$currentChunk, &$chunkOffset) {
            $this->scheduleGeneratorWrite(
                $operation,
                $stream,
                $generator,
                $bytesWritten,
                $currentChunk,
                $chunkOffset
            );
        });
    }

    /**
     * Handle reading a file as a generator for memory-efficient streaming.
     */
    private function handleReadAsGenerator(FileOperation $operation): void
    {
        if ($operation->isCancelled()) {
            return;
        }

        $path = $operation->getPath();
        $options = $operation->getOptions();

        if (! file_exists($path)) {
            $operation->executeCallback("File does not exist: $path");

            return;
        }

        if (! is_readable($path)) {
            $operation->executeCallback("File is not readable: $path");

            return;
        }

        $readMode = $options['read_mode'] ?? 'chunks';

        if ($readMode === 'lines') {
            $generator = $this->createLineGenerator($path, $options, $operation);
        } else {
            $generator = $this->createChunkGenerator($path, $options, $operation);
        }

        $operation->executeCallback(null, $generator);
    }

    /**
     * Create a generator that yields file chunks.
     *
     * @param  array<string,mixed>  $options  ['chunk_size'=>int, 'offset'=>int, 'length'=>int].
     */
    private function createChunkGenerator(string $path, array $options, FileOperation $operation): Generator
    {
        $chunkSizeRaw = $options['chunk_size'] ?? self::CHUNK_SIZE;
        $chunkSize = is_numeric($chunkSizeRaw) ? max(1, (int) $chunkSizeRaw) : self::CHUNK_SIZE;

        $offsetRaw = $options['offset'] ?? 0;
        $offset = is_numeric($offsetRaw) ? (int) $offsetRaw : 0;

        $lengthRaw = $options['length'] ?? null;
        $maxLength = is_numeric($lengthRaw) ? max(0, (int) $lengthRaw) : null;

        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException("Failed to open file: $path");
        }

        if ($offset > 0) {
            fseek($stream, $offset);
        }

        $bytesRead = 0;
        $chunkCounter = 0;

        try {
            while (! feof($stream)) {
                if ($operation->isCancelled()) {
                    break;
                }

                $readSize = $chunkSize;
                if ($maxLength !== null) {
                    $remaining = $maxLength - $bytesRead;
                    if ($remaining <= 0) {
                        break;
                    }
                    $readSize = min($chunkSize, $remaining);
                }

                $chunk = fread($stream, $readSize);
                if ($chunk === false) {
                    throw new RuntimeException("Failed to read from file: $path");
                }

                if ($chunk === '') {
                    break;
                }

                $bytesRead += strlen($chunk);
                yield $chunk;

                $chunkCounter++;
                if ($chunkCounter % 10 === 0) {
                    EventLoop::getInstance()->addTimer(0, function () {});
                }
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * Create a generator that yields file lines.
     *
     * @param  array<string,mixed>  $options
     */
    private function createLineGenerator(string $path, array $options, FileOperation $operation): Generator
    {
        $chunkSizeRaw = $options['chunk_size'] ?? self::CHUNK_SIZE;
        $chunkSize = is_numeric($chunkSizeRaw) ? max(1, (int) $chunkSizeRaw) : self::CHUNK_SIZE;

        $trim = $options['trim'] ?? false;
        $trim = is_scalar($trim) && (bool) $trim;

        $skipEmpty = $options['skip_empty'] ?? false;
        $skipEmpty = is_scalar($skipEmpty) && (bool) $skipEmpty;

        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException("Failed to open file: $path");
        }

        $buffer = '';
        $lineCount = 0;

        try {
            while (! feof($stream)) {
                if ($operation->isCancelled()) {
                    break;
                }

                $chunk = fread($stream, $chunkSize);
                if ($chunk === false) {
                    throw new RuntimeException("Failed to read from file: $path");
                }

                $buffer .= $chunk;

                // Process complete lines in buffer
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos + 1);
                    $buffer = substr($buffer, $pos + 1);

                    if ($trim) {
                        $line = trim($line);
                    }

                    if ($skipEmpty && $line === '') {
                        continue;
                    }

                    yield $line;
                    $lineCount++;

                    // Yield control periodically (every 100 lines)
                    if ($lineCount % 100 === 0) {
                        EventLoop::getInstance()->addTimer(0, function () {});
                    }
                }
            }

            // Yield any remaining content in buffer (last line without newline)
            if ($buffer !== '') {
                if ($trim) {
                    $buffer = trim($buffer);
                }

                if (! $skipEmpty || $buffer !== '') {
                    yield $buffer;
                }
            }
        } finally {
            fclose($stream);
        }
    }

    private function executeStreamingOperation(FileOperation $operation): void
    {
        switch ($operation->getType()) {
            case 'read':
                $this->handleStreamingRead($operation);

                break;
            case 'write':
                $this->handleStreamingWrite($operation);

                break;
            case 'copy':
                $this->handleStreamingCopy($operation);

                break;
            default:
                $this->executeOperationSync($operation);
        }
    }

    private function handleStreamingRead(FileOperation $operation): void
    {
        if ($operation->isCancelled()) {
            return;
        }

        $path = $operation->getPath();
        $options = $operation->getOptions();

        if (! file_exists($path)) {
            $operation->executeCallback("File does not exist: $path");

            return;
        }

        if (! is_readable($path)) {
            $operation->executeCallback("File is not readable: $path");

            return;
        }

        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            $operation->executeCallback("Failed to open file: $path");

            return;
        }

        // Handle offset
        $offsetRaw = $options['offset'] ?? 0;
        $offset = is_numeric($offsetRaw) ? (int) $offsetRaw : 0;
        if ($offset > 0) {
            fseek($stream, $offset);
        }

        $lengthRaw = $options['length'] ?? null;
        $length = is_numeric($lengthRaw) ? max(0, (int) $lengthRaw) : null;

        $content = '';
        $bytesRead = 0;

        $this->scheduleStreamRead($operation, $stream, $content, $bytesRead, $length);
    }

    /**
     * @param  resource  $stream
     */
    private function scheduleStreamRead(FileOperation $operation, $stream, string &$content, int &$bytesRead, ?int $maxLength): void
    {
        if ($operation->isCancelled()) {
            fclose($stream);

            return;
        }

        if (feof($stream) || ($maxLength !== null && $bytesRead >= $maxLength)) {
            fclose($stream);
            $operation->executeCallback(null, $content);

            return;
        }

        $chunkSize = self::CHUNK_SIZE;
        if ($maxLength !== null) {
            $chunkSize = min($chunkSize, $maxLength - $bytesRead);
        }

        if ($chunkSize <= 0) {
            fclose($stream);
            $operation->executeCallback(null, $content);

            return;
        }

        $chunk = fread($stream, $chunkSize);
        if ($chunk === false) {
            fclose($stream);
            $operation->executeCallback('Failed to read from file');

            return;
        }

        $content .= $chunk;
        $bytesRead += strlen($chunk);

        EventLoop::getInstance()->addTimer(0, function () use ($operation, $stream, &$content, &$bytesRead, $maxLength) {
            $this->scheduleStreamRead($operation, $stream, $content, $bytesRead, $maxLength);
        });
    }

    private function handleStreamingWrite(FileOperation $operation): void
    {
        if ($operation->isCancelled()) {
            return;
        }

        $path = $operation->getPath();
        $data = $operation->getData();
        if (! is_scalar($data)) {
            $operation->executeCallback('Invalid data provided for writing. Must be a scalar value.');

            return;
        }
        $data = (string) $data;
        $options = $operation->getOptions();

        $createDirs = $options['create_directories'] ?? false;
        if (is_scalar($createDirs) && (bool) $createDirs) {
            $dir = dirname($path);
            if (! is_dir($dir)) {
                if (! mkdir($dir, 0755, true)) {
                    $operation->executeCallback("Failed to create directory: $dir");

                    return;
                }
            }
        }

        $mode = 'wb';
        $flagsRaw = $options['flags'] ?? 0;
        if (is_numeric($flagsRaw) && (((int) $flagsRaw & FILE_APPEND) !== 0)) {
            $mode = 'ab';
        }

        $stream = @fopen($path, $mode);
        if ($stream === false) {
            $operation->executeCallback("Failed to open file for writing: $path");

            return;
        }

        $dataLength = strlen($data);
        $bytesWritten = 0;

        $this->scheduleStreamWrite($operation, $stream, $data, $bytesWritten, $dataLength);
    }

    /**
     * @param  resource  $stream
     */
    private function scheduleStreamWrite(FileOperation $operation, $stream, string $data, int &$bytesWritten, int $totalLength): void
    {
        if ($operation->isCancelled()) {
            fclose($stream);

            return;
        }

        if ($bytesWritten >= $totalLength) {
            fclose($stream);
            $operation->executeCallback(null, $bytesWritten);

            return;
        }

        $chunkSize = min(self::CHUNK_SIZE, $totalLength - $bytesWritten);
        $chunk = substr($data, $bytesWritten, $chunkSize);

        $written = fwrite($stream, $chunk);
        if ($written === false) {
            fclose($stream);
            $operation->executeCallback('Failed to write to file');

            return;
        }

        $bytesWritten += $written;

        EventLoop::getInstance()->addTimer(0, function () use ($operation, $stream, $data, &$bytesWritten, $totalLength) {
            $this->scheduleStreamWrite($operation, $stream, $data, $bytesWritten, $totalLength);
        });
    }

    private function handleStreamingCopy(FileOperation $operation): void
    {
        if ($operation->isCancelled()) {
            return;
        }

        $sourcePath = $operation->getPath();
        $destinationPath = $operation->getData();

        if (! is_string($destinationPath) || $destinationPath === '') {
            $operation->executeCallback('Invalid destination path provided for copy.');

            return;
        }

        if (! file_exists($sourcePath)) {
            $operation->executeCallback("Source file does not exist: $sourcePath");

            return;
        }

        $sourceStream = @fopen($sourcePath, 'rb');
        if ($sourceStream === false) {
            $operation->executeCallback("Failed to open source file: $sourcePath");

            return;
        }

        $destStream = @fopen($destinationPath, 'wb');
        if ($destStream === false) {
            fclose($sourceStream);
            $operation->executeCallback("Failed to open destination file: {$destinationPath}");

            return;
        }

        $this->scheduleStreamCopy($operation, $sourceStream, $destStream);
    }

    /**
     * @param  resource  $sourceStream
     * @param  resource  $destStream
     */
    private function scheduleStreamCopy(FileOperation $operation, $sourceStream, $destStream): void
    {
        if ($operation->isCancelled()) {
            fclose($sourceStream);
            fclose($destStream);

            return;
        }

        if (feof($sourceStream)) {
            fclose($sourceStream);
            fclose($destStream);
            $operation->executeCallback(null, true);

            return;
        }

        $chunk = fread($sourceStream, self::CHUNK_SIZE);
        if ($chunk === false) {
            fclose($sourceStream);
            fclose($destStream);
            $operation->executeCallback('Failed to read from source file');

            return;
        }

        $written = fwrite($destStream, $chunk);
        if ($written === false) {
            fclose($sourceStream);
            fclose($destStream);
            $operation->executeCallback('Failed to write to destination file');

            return;
        }

        EventLoop::getInstance()->addTimer(0, function () use ($operation, $sourceStream, $destStream) {
            $this->scheduleStreamCopy($operation, $sourceStream, $destStream);
        });
    }

    private function executeOperationSync(FileOperation $operation): void
    {
        if ($operation->isCancelled()) {
            return;
        }

        try {
            switch ($operation->getType()) {
                case 'read':
                    $this->handleRead($operation);

                    break;
                case 'write':
                    $this->handleWrite($operation);

                    break;
                case 'write_generator':
                    $this->handleWriteFromGenerator($operation);

                    break;
                case 'read_generator':
                    $this->handleReadAsGenerator($operation);

                    break;
                case 'append':
                    $this->handleAppend($operation);

                    break;
                case 'delete':
                    $this->handleDelete($operation);

                    break;
                case 'exists':
                    $this->handleExists($operation);

                    break;
                case 'stat':
                    $this->handleStat($operation);

                    break;
                case 'mkdir':
                    $this->handleMkdir($operation);

                    break;
                case 'rmdir':
                    $this->handleRmdir($operation);

                    break;
                case 'copy':
                    $this->handleCopy($operation);

                    break;
                case 'rename':
                    $this->handleRename($operation);

                    break;
                default:
                    throw new InvalidArgumentException("Unknown operation type: {$operation->getType()}");
            }
        } catch (Throwable $e) {
            $operation->executeCallback($e->getMessage());
        }
    }

    private function handleRead(FileOperation $operation): void
    {
        $path = $operation->getPath();
        $options = $operation->getOptions();

        if (! file_exists($path)) {
            throw new RuntimeException("File does not exist: $path");
        }

        if (! is_readable($path)) {
            throw new RuntimeException("File is not readable: $path");
        }

        $offsetRaw = $options['offset'] ?? 0;
        $offset = is_numeric($offsetRaw) ? (int) $offsetRaw : 0;

        $lengthRaw = $options['length'] ?? null;
        $length = is_numeric($lengthRaw) ? max(0, (int) $lengthRaw) : null;

        if ($length !== null) {
            $content = file_get_contents($path, false, null, $offset, $length);
        } else {
            $content = file_get_contents($path, false, null, $offset);
        }

        if ($content === false) {
            throw new RuntimeException("Failed to read file: $path");
        }

        $operation->executeCallback(null, $content);
    }

    private function handleWrite(FileOperation $operation): void
    {
        $path = $operation->getPath();
        $data = $operation->getData();
        $options = $operation->getOptions();

        $flagsRaw = $options['flags'] ?? 0;
        $flags = is_numeric($flagsRaw) ? (int) $flagsRaw : 0;

        $createDirs = $options['create_directories'] ?? false;
        if (is_scalar($createDirs) && (bool) $createDirs) {
            $dir = dirname($path);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        $result = file_put_contents($path, $data, $flags);

        if ($result === false) {
            throw new RuntimeException("Failed to write file: $path");
        }

        $operation->executeCallback(null, $result);
    }

    private function handleAppend(FileOperation $operation): void
    {
        $path = $operation->getPath();
        $data = $operation->getData();

        $result = file_put_contents($path, $data, FILE_APPEND | LOCK_EX);

        if ($result === false) {
            throw new RuntimeException("Failed to append to file: $path");
        }

        $operation->executeCallback(null, $result);
    }

    private function handleDelete(FileOperation $operation): void
    {
        $path = $operation->getPath();

        if (! file_exists($path)) {
            $operation->executeCallback(null, true);

            return;
        }

        $result = unlink($path);

        if ($result === false) {
            throw new RuntimeException("Failed to delete file: $path");
        }

        $operation->executeCallback(null, true);
    }

    private function handleExists(FileOperation $operation): void
    {
        $path = $operation->getPath();
        $exists = file_exists($path);

        $operation->executeCallback(null, $exists);
    }

    private function handleStat(FileOperation $operation): void
    {
        $path = $operation->getPath();

        if (! file_exists($path)) {
            throw new RuntimeException("File does not exist: $path");
        }

        $stat = stat($path);

        if ($stat === false) {
            throw new RuntimeException("Failed to get file stats: $path");
        }

        $operation->executeCallback(null, $stat);
    }

    private function handleMkdir(FileOperation $operation): void
    {
        $path = $operation->getPath();
        $options = $operation->getOptions();

        $modeRaw = $options['mode'] ?? 0755;
        $mode = is_numeric($modeRaw) ? (int) $modeRaw : 0755;

        $recursiveRaw = $options['recursive'] ?? false;
        $recursive = is_scalar($recursiveRaw) ? (bool) $recursiveRaw : false;

        if (is_dir($path)) {
            $operation->executeCallback(null, true);

            return;
        }

        $result = mkdir($path, $mode, $recursive);

        if ($result === false) {
            throw new RuntimeException("Failed to create directory: $path");
        }

        $operation->executeCallback(null, true);
    }

    private function handleRmdir(FileOperation $operation): void
    {
        $path = $operation->getPath();

        if (! is_dir($path)) {
            $operation->executeCallback(null, true);

            return;
        }

        $files = array_diff(scandir($path), ['.', '..']);

        if (count($files) > 0) {
            $this->removeDirectoryRecursive($path, $operation);
        } else {
            $result = rmdir($path);
            if ($result === false) {
                throw new RuntimeException("Failed to remove directory: $path");
            }
        }

        if ($operation->isCancelled()) {
            return;
        }

        $operation->executeCallback(null, true);
    }

    private function removeDirectoryRecursive(string $dir, FileOperation $operation): void
    {
        if ($operation->isCancelled() || ! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            if ($operation->isCancelled()) {
                return;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDirectoryRecursive($path, $operation);
            } else {
                unlink($path);
            }
        }

        if ($operation->isCancelled()) {
            return;
        }

        rmdir($dir);
    }

    private function handleCopy(FileOperation $operation): void
    {
        $sourcePath = $operation->getPath();
        $destinationPath = $operation->getData();

        if (! is_string($destinationPath)) {
            throw new InvalidArgumentException('Destination path for copy must be a string.');
        }

        if (! file_exists($sourcePath)) {
            throw new RuntimeException("Source file does not exist: $sourcePath");
        }

        $result = copy($sourcePath, $destinationPath);

        if ($result === false) {
            throw new RuntimeException("Failed to copy file from {$sourcePath} to {$destinationPath}");
        }

        $operation->executeCallback(null, true);
    }

    private function handleRename(FileOperation $operation): void
    {
        $oldPath = $operation->getPath();
        $newPath = $operation->getData();

        if (! is_string($newPath)) {
            throw new InvalidArgumentException('New path for rename must be a string.');
        }

        if (! file_exists($oldPath)) {
            throw new RuntimeException("Source file does not exist: $oldPath");
        }

        $result = rename($oldPath, $newPath);

        if ($result === false) {
            throw new RuntimeException("Failed to rename file from {$oldPath} to {$newPath}");
        }

        $operation->executeCallback(null, true);
    }
}
