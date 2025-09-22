<?php

namespace Hibla\EventLoop\Managers\UV;

use Hibla\EventLoop\Managers\StreamManager;
use Hibla\EventLoop\ValueObjects\StreamWatcher;

/**
 * UV-based stream manager using libuv for efficient I/O polling
 */
final class UVStreamManager extends StreamManager
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
        $watcherId = $this->generateWatcherId();

        $socket = is_resource($stream) ? $stream : null;
        if (! $socket) {
            throw new \InvalidArgumentException('Invalid stream resource');
        }

        $uvPoll = \uv_poll_init_socket($this->uvLoop, $socket);

        $this->uvPolls[$watcherId] = $uvPoll;

        $events = match ($type) {
            StreamWatcher::TYPE_READ => \UV::READABLE,
            StreamWatcher::TYPE_WRITE => \UV::WRITABLE,
            default => \UV::READABLE
        };

        \uv_poll_start($uvPoll, $events, function ($poll, $status, $events) use ($callback, $stream, $type) {
            if ($status < 0) {
                error_log('UV poll error: '.\uv_strerror($status));

                return;
            }

            try {
                $callback($stream, $type);
            } catch (\Throwable $e) {
                error_log('UV stream callback error: '.$e->getMessage());
            }
        });

        return parent::addStreamWatcher($stream, $callback, $type);
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
     * UV handles the actual processing through callbacks
     */
    public function processStreams(): void
    {
        if (parent::hasWatchers()) {
            parent::processStreams();
        }
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
        return ! empty($this->uvPolls) || parent::hasWatchers();
    }

    private function generateWatcherId(): string
    {
        return uniqid('uv_stream_', true);
    }
}
