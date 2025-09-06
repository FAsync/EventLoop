<?php

namespace Hibla\EventLoop\Managers;

use Hibla\EventLoop\IOHandlers\Timer\TimerExecutionHandler;
use Hibla\EventLoop\IOHandlers\Timer\TimerScheduleHandler;
use Hibla\EventLoop\ValueObjects\Timer;

/**
 * Manages the lifecycle of all timers for the event loop.
 *
 * This class handles the creation, cancellation, and processing of timers,
 * determining when they are ready to be executed.
 */
class TimerManager
{
    /**
     * A map of active timers, keyed by their unique string ID.
     *
     * @var array<string, Timer>
     */
    private array $timers = [];

    private readonly TimerExecutionHandler $executionHandler;
    private readonly TimerScheduleHandler $scheduleHandler;

    public function __construct()
    {
        $this->executionHandler = new TimerExecutionHandler;
        $this->scheduleHandler = new TimerScheduleHandler;
    }

    /**
     * Adds a new timer to the manager.
     *
     * @param  float  $delay  The delay in seconds before the timer should fire.
     * @param  callable  $callback  The callback to execute when the timer fires.
     * @return string The unique ID of the created timer, which can be used for cancellation.
     */
    public function addTimer(float $delay, callable $callback): string
    {
        $timer = $this->scheduleHandler->createTimer($delay, $callback);
        $this->timers[$timer->getId()] = $timer;

        return $timer->getId();
    }

    /**
     * Cancels a pending timer by its unique ID.
     *
     * @param  string  $timerId  The ID of the timer to cancel.
     * @return bool True if the timer was found and canceled, false otherwise.
     */
    public function cancelTimer(string $timerId): bool
    {
        if (isset($this->timers[$timerId])) {
            unset($this->timers[$timerId]);

            return true;
        }

        return false;
    }

    /**
     * Checks if a timer exists and is currently active.
     *
     * @param  string  $timerId  The ID of the timer to check.
     * @return bool True if the timer exists.
     */
    public function hasTimer(string $timerId): bool
    {
        return isset($this->timers[$timerId]);
    }

    /**
     * Processes all timers and executes any that are ready.
     *
     * This method should be called on each tick of the event loop. It delegates
     * to the execution handler, which will modify the timers array by reference.
     *
     * @return bool True if at least one timer was executed.
     */
    public function processTimers(): bool
    {
        $currentTime = microtime(true);

        return $this->executionHandler->executeReadyTimers($this->timers, $currentTime);
    }

    /**
     * Checks if there are any pending timers.
     *
     * @return bool True if there is at least one active timer.
     */
    public function hasTimers(): bool
    {
        return count($this->timers) > 0;
    }

    /**
     * Calculates the time in seconds until the next timer is due to fire.
     *
     * This is used by the event loop to determine the optimal sleep duration.
     *
     * @return float|null The delay until the next timer, or null if no timers are pending.
     */
    public function getNextTimerDelay(): ?float
    {
        $currentTime = microtime(true);

        return $this->scheduleHandler->calculateDelay($this->timers, $currentTime);
    }

    /**
     * Clear all pending timers.
     * Used during forced shutdown to prevent hanging.
     */
    public function clearAllTimers(): void
    {
        $this->timers = [];
    }
}
