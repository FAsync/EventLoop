<?php

namespace Hibla\EventLoop\Managers\Uv;

use Hibla\EventLoop\Managers\TimerManager;

final class UvTimerManager extends TimerManager
{
    private $uvLoop;
    private array $uvTimers = [];
    private array $timerCallbacks = [];

    public function __construct($uvLoop = null)
    {
        parent::__construct();
        $this->uvLoop = $uvLoop ?? \uv_default_loop();
    }

    public function addTimer(float $delay, callable $callback): string
    {
        $timerId = uniqid('uv_timer_', true);
        $this->timerCallbacks[$timerId] = $callback;

        $uvTimer = \uv_timer_init($this->uvLoop);
        $this->uvTimers[$timerId] = $uvTimer;

        $delayMs = (int) round($delay * 1000);

        if ($delay > 0 && $delayMs === 0) {
            $delayMs = 1;
        }

        \uv_timer_start($uvTimer, $delayMs, 0, function ($timer) use ($callback, $timerId) {
            try {
                $callback();
            } catch (\Throwable $e) {
                error_log("UV Timer callback error: " . $e->getMessage());
            } finally {
                if (isset($this->uvTimers[$timerId])) {
                    \uv_close($timer);
                    unset($this->uvTimers[$timerId]);
                    unset($this->timerCallbacks[$timerId]);
                }
            }
        });

        return $timerId;
    }

    public function cancelTimer(string $timerId): bool
    {
        if (isset($this->uvTimers[$timerId])) {
            $uvTimer = $this->uvTimers[$timerId];
            \uv_timer_stop($uvTimer);
            \uv_close($uvTimer);
            unset($this->uvTimers[$timerId]);
            unset($this->timerCallbacks[$timerId]);
            return true;
        }

        return parent::cancelTimer($timerId);
    }

    public function hasTimer(string $timerId): bool
    {
        return isset($this->uvTimers[$timerId]) || parent::hasTimer($timerId);
    }

    public function processTimers(): bool
    {
        return !empty($this->uvTimers) || parent::processTimers();
    }

    public function hasTimers(): bool
    {
        return !empty($this->uvTimers) || parent::hasTimers();
    }

    public function clearAllTimers(): void
    {
        foreach ($this->uvTimers as $timerId => $uvTimer) {
            \uv_timer_stop($uvTimer);
            \uv_close($uvTimer);
        }
        $this->uvTimers = [];
        $this->timerCallbacks = [];

        parent::clearAllTimers();
    }

    public function getNextTimerDelay(): ?float
    {
        if (!empty($this->uvTimers)) {
            return null;
        }

        return parent::getNextTimerDelay();
    }
}
