<?php

namespace Hibla\EventLoop\UV\Managers;

use Hibla\EventLoop\Interfaces\SocketManagerInterface;
use Hibla\EventLoop\Managers\SocketManager;

/**
 * UV-based socket manager using libuv for efficient socket I/O
 */
final class UVSocketManager extends SocketManager implements SocketManagerInterface
{
    /** @var \UVLoop|null */
    private $uvLoop;

    /** @var array<int, \UVPoll> */
    private array $uvTcpHandles = [];

    /** @var array<int, mixed> */
    private array $uvUdpHandles = [];

    /**
     * @param  \UVLoop|null  $uvLoop
     */
    public function __construct($uvLoop = null)
    {
        $this->uvLoop = $uvLoop ?? \uv_default_loop();
    }

    /**
     * {@inheritdoc}
     */
    public function addReadWatcher(mixed $socket, callable $callback): void
    {
        if ($this->addUvReadWatcher($socket, $callback)) {
            return;
        }

        parent::addReadWatcher($socket, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function addWriteWatcher(mixed $socket, callable $callback): void
    {
        if ($this->addUvWriteWatcher($socket, $callback)) {
            return;
        }

        parent::addWriteWatcher($socket, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function processSockets(): bool
    {
        return parent::processSockets();
    }

    /**
     * {@inheritdoc}
     */
    public function hasWatchers(): bool
    {
        return count($this->uvTcpHandles) > 0 || count($this->uvUdpHandles) > 0 || parent::hasWatchers();
    }

    /**
     * {@inheritdoc}
     */
    public function clearAllWatchers(): void
    {
        foreach ($this->uvTcpHandles as $handle) {
            \uv_poll_stop($handle);
            \uv_close($handle);
        }
        foreach ($this->uvUdpHandles as $handle) {
            /** @phpstan-ignore-next-line */
            \uv_close($handle);
        }

        $this->uvTcpHandles = [];
        $this->uvUdpHandles = [];

        parent::clearAllWatchers();
    }

    /**
     * {@inheritdoc}
     */
    public function removeReadWatcher(mixed $socket): void
    {
        $socketId = (int) $socket;

        if (isset($this->uvTcpHandles[$socketId])) {
            $handle = $this->uvTcpHandles[$socketId];
            \uv_poll_stop($handle);
            \uv_close($handle);
            unset($this->uvTcpHandles[$socketId]);
        }

        parent::removeReadWatcher($socket);
    }

    /**
     * {@inheritdoc}
     */
    public function removeWriteWatcher(mixed $socket): void
    {
        $socketId = (int) $socket;

        if (isset($this->uvTcpHandles[$socketId])) {
            $handle = $this->uvTcpHandles[$socketId];
            \uv_poll_stop($handle);
            \uv_close($handle);
            unset($this->uvTcpHandles[$socketId]);
        }

        parent::removeWriteWatcher($socket);
    }

    /**
     * {@inheritdoc}
     */
    public function clearAllWatchersForSocket(mixed $socket): void
    {
        $this->removeReadWatcher($socket);
        $this->removeWriteWatcher($socket);
        parent::clearAllWatchersForSocket($socket);
    }

    /**
     * Add a UV read watcher for the given socket.
     */
    private function addUvReadWatcher(mixed $socket, callable $callback): bool
    {
        if (! is_resource($socket)) {
            return false;
        }

        try {
            $socketId = (int) $socket;

            if (! function_exists('uv_poll_init_socket')) {
                return false;
            }

            /** @var \UVPoll $uvPoll */
            $uvPoll = \uv_poll_init_socket($this->uvLoop, $socket);

            \uv_poll_start($uvPoll, \UV::READABLE, function ($poll, $status, $events) use ($callback, $socketId) {
                if ($status < 0) {
                    $statusInt = is_int($status) ? $status : 0;
                    error_log('UV read poll error: '.\uv_strerror($statusInt));

                    return;
                }

                try {
                    $callback();
                } catch (\Throwable $e) {
                    error_log('UV read callback error: '.$e->getMessage());
                }

                /** @var \UVPoll $poll */
                \uv_poll_stop($poll);
                \uv_close($poll);
                unset($this->uvTcpHandles[$socketId]);
            });

            $this->uvTcpHandles[$socketId] = $uvPoll;

            return true;
        } catch (\Throwable $e) {
            error_log('Failed to create UV read watcher: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Add a UV write watcher for the given socket.
     */
    private function addUvWriteWatcher(mixed $socket, callable $callback): bool
    {
        if (! is_resource($socket)) {
            return false;
        }

        try {
            $socketId = (int) $socket;

            if (! function_exists('uv_poll_init_socket')) {
                return false;
            }

            /** @var \UVPoll $uvPoll */
            $uvPoll = \uv_poll_init_socket($this->uvLoop, $socket);

            \uv_poll_start($uvPoll, \UV::WRITABLE, function ($poll, $status, $events) use ($callback, $socketId) {
                if ($status < 0) {
                    $statusInt = is_int($status) ? $status : 0;
                    error_log('UV write poll error: '.\uv_strerror($statusInt));

                    return;
                }

                try {
                    $callback();
                } catch (\Throwable $e) {
                    error_log('UV write callback error: '.$e->getMessage());
                }

                /** @var \UVPoll $poll */
                \uv_poll_stop($poll);
                \uv_close($poll);
                unset($this->uvTcpHandles[$socketId]);
            });

            $this->uvTcpHandles[$socketId] = $uvPoll;

            return true;
        } catch (\Throwable $e) {
            error_log('Failed to create UV write watcher: '.$e->getMessage());

            return false;
        }
    }
}
