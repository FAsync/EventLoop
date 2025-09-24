<?php

namespace Hibla\EventLoop\UV\Managers;

use Hibla\EventLoop\Interfaces\TimerManagerInterface;
use Hibla\EventLoop\Managers\TimerManager;

final class UVTimerManager extends TimerManager implements TimerManagerInterface
{
    private $uvLoop;
    private array $uvTimers = [];
    private array $timerCallbacks = [];
    private array $periodicTimers = [];

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
                error_log('UV Timer callback error: '.$e->getMessage());
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

    public function addPeriodicTimer(float $interval, callable $callback, ?int $maxExecutions = null): string
    {
        $timerId = uniqid('uv_periodic_timer_', true);
        $executionCount = 0;

        $this->timerCallbacks[$timerId] = $callback;
        $this->periodicTimers[$timerId] = [
            'max_executions' => $maxExecutions,
            'execution_count' => 0,
            'interval' => $interval,
        ];

        $uvTimer = \uv_timer_init($this->uvLoop);
        $this->uvTimers[$timerId] = $uvTimer;

        $intervalMs = (int) round($interval * 1000);

        if ($interval > 0 && $intervalMs === 0) {
            $intervalMs = 1;
        }

        \uv_timer_start($uvTimer, $intervalMs, $intervalMs, function ($timer) use ($callback, $timerId, $maxExecutions, &$executionCount) {
            try {
                $executionCount++;
                $this->periodicTimers[$timerId]['execution_count'] = $executionCount;

                $callback();

                if ($maxExecutions !== null && $executionCount >= $maxExecutions) {
                    $this->cancelTimer($timerId);
                }
            } catch (\Throwable $e) {
                error_log('UV Periodic Timer callback error: '.$e->getMessage());
                $this->cancelTimer($timerId);
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
            unset($this->periodicTimers[$timerId]);

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
        return ! empty($this->uvTimers) || parent::processTimers();
    }

    public function hasTimers(): bool
    {
        return ! empty($this->uvTimers) || parent::hasTimers();
    }

    public function clearAllTimers(): void
    {
        foreach ($this->uvTimers as $timerId => $uvTimer) {
            \uv_timer_stop($uvTimer);
            \uv_close($uvTimer);
        }
        $this->uvTimers = [];
        $this->timerCallbacks = [];
        $this->periodicTimers = [];

        parent::clearAllTimers();
    }

    public function getNextTimerDelay(): ?float
    {
        if (! empty($this->uvTimers)) {
            return null;
        }

        return parent::getNextTimerDelay();
    }

    public function getTimerStats(): array
    {
        $parentStats = parent::getTimerStats();

        $uvRegularCount = 0;
        $uvPeriodicCount = 0;
        $uvTotalExecutions = 0;

        foreach ($this->uvTimers as $timerId => $timer) {
            if (isset($this->periodicTimers[$timerId])) {
                $uvPeriodicCount++;
                $uvTotalExecutions += $this->periodicTimers[$timerId]['execution_count'];
            } else {
                $uvRegularCount++;
            }
        }

        return [
            'regular_timers' => $parentStats['regular_timers'] + $uvRegularCount,
            'periodic_timers' => $parentStats['periodic_timers'] + $uvPeriodicCount,
            'total_timers' => $parentStats['total_timers'] + count($this->uvTimers),
            'total_periodic_executions' => $parentStats['total_periodic_executions'] + $uvTotalExecutions,
            'uv_timers' => count($this->uvTimers),
            'uv_regular_timers' => $uvRegularCount,
            'uv_periodic_timers' => $uvPeriodicCount,
        ];
    }

    public function getTimerInfo(string $timerId): ?array
    {
        if (isset($this->uvTimers[$timerId])) {
            $baseInfo = [
                'id' => $timerId,
                'backend' => 'uv',
                'is_active' => true,
            ];

            if (isset($this->periodicTimers[$timerId])) {
                $periodicInfo = $this->periodicTimers[$timerId];
                $baseInfo['type'] = 'periodic';
                $baseInfo['interval'] = $periodicInfo['interval'];
                $baseInfo['execution_count'] = $periodicInfo['execution_count'];
                $baseInfo['max_executions'] = $periodicInfo['max_executions'];
                $baseInfo['remaining_executions'] = $periodicInfo['max_executions'] !== null
                    ? max(0, $periodicInfo['max_executions'] - $periodicInfo['execution_count'])
                    : null;
            } else {
                $baseInfo['type'] = 'regular';
            }

            return $baseInfo;
        }

        return parent::getTimerInfo($timerId);
    }
}
