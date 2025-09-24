<?php

namespace Hibla\EventLoop\UV\Managers;

use Hibla\EventLoop\Interfaces\StreamManagerInterface;
use Hibla\EventLoop\Managers\StreamManager;
use Hibla\EventLoop\ValueObjects\StreamWatcher;

/**
 * UV-based stream manager using libuv for efficient I/O polling
 * Falls back to regular stream_select for non-socket resources
 */
final class UVStreamManager extends StreamManager implements StreamManagerInterface
{
    private $uvLoop;
    private array $uvPolls = [];

    public function __construct($uvLoop = null)
    {
        parent::__construct();
        $this->uvLoop = $uvLoop ?? \uv_default_loop();
    }

    public function addStreamWatcher($stream, callable $callback, string $type = StreamWatcher::TYPE_READ): string
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Invalid stream resource');
        }

        $watcherId = parent::addStreamWatcher($stream, $callback, $type);

        // Only try UV polling for socket-like resources
        $metaData = stream_get_meta_data($stream);
        if ($this->canUseUvPolling($metaData)) {
            try {
                $this->addUvSocketWatcher($watcherId, $stream, $callback, $type);
            } catch (\Throwable $e) {
                // If UV fails, just use parent's stream_select method
                error_log('UV polling failed, falling back to stream_select: ' . $e->getMessage());
            }
        }

        return $watcherId;
    }

    private function canUseUvPolling(array $metaData): bool
    {
        $streamType = $metaData['stream_type'] ?? '';
        return in_array($streamType, ['tcp_socket', 'udp_socket', 'unix_socket', 'tcp_socket/ssl']);
    }

    private function addUvSocketWatcher(string $watcherId, $stream, callable $callback, string $type): void
    {
        $uvPoll = \uv_poll_init_socket($this->uvLoop, $stream);
        
        if ($uvPoll === false) {
            throw new \RuntimeException('Failed to initialize UV poll for socket');
        }

        $this->uvPolls[$watcherId] = $uvPoll;

        $events = match ($type) {
            StreamWatcher::TYPE_READ => \UV::READABLE,
            StreamWatcher::TYPE_WRITE => \UV::WRITABLE,
            default => \UV::READABLE
        };

        \uv_poll_start($uvPoll, $events, function ($poll, $status, $events) use ($callback, $stream, $type) {
            if ($status < 0) {
                error_log('UV poll error: ' . \uv_strerror($status));
                return;
            }

            try {
                $callback($stream, $type);
            } catch (\Throwable $e) {
                error_log('UV stream callback error: ' . $e->getMessage());
            }
        });
    }

    public function removeStreamWatcher(string $watcherId): bool
    {
        if (isset($this->uvPolls[$watcherId])) {
            $uvPoll = $this->uvPolls[$watcherId];
            \uv_poll_stop($uvPoll);
            \uv_close($uvPoll);
            unset($this->uvPolls[$watcherId]);
        }

        return parent::removeStreamWatcher($watcherId);
    }

    /**
     * Process streams - MUST return void to match parent signature
     * UV handles socket streams through callbacks
     * Parent handles file streams through stream_select
     */
    public function processStreams(): void
    {
        parent::processStreams();
    }

    public function clearAllWatchers(): void
    {
        foreach ($this->uvPolls as $uvPoll) {
            \uv_poll_stop($uvPoll);
            \uv_close($uvPoll);
        }
        $this->uvPolls = [];

        parent::clearAllWatchers();
    }

    public function hasWatchers(): bool
    {
        return !empty($this->uvPolls) || parent::hasWatchers();
    }

    /**
     * Check if a stream watcher is using UV polling
     */
    public function isUsingUvPolling(string $watcherId): bool
    {
        return isset($this->uvPolls[$watcherId]);
    }

    /**
     * Get count of UV-managed watchers
     */
    public function getUvWatcherCount(): int
    {
        return count($this->uvPolls);
    }

    /**
     * Get count of regular stream watchers (handled by parent)
     */
    public function getRegularWatcherCount(): int
    {
        return parent::hasWatchers() ? 1 : 0; // Parent doesn't expose count, so we estimate
    }
}