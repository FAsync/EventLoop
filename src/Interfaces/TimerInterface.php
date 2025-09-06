<?php

namespace Hibla\EventLoop\Interfaces;

/**
 * Represents a scheduled timer in the event loop.
 *
 * Timers are used to execute callbacks after a specified delay.
 * They are managed by the event loop and can be queried for readiness
 * and executed when their time arrives.
 */
interface TimerInterface
{
    /**
     * Gets the unique identifier for this timer.
     *
     * @return string The timer's unique ID
     */
    public function getId(): string;

    /**
     * Gets the callback function to be executed when the timer fires.
     *
     * @return callable The callback function
     */
    public function getCallback(): callable;

    /**
     * Gets the timestamp when this timer should be executed.
     *
     * @return float The execution timestamp (typically a microtime float)
     */
    public function getExecuteAt(): float;

    /**
     * Checks if the timer is ready to be executed at the current time.
     *
     * @param  float  $currentTime  The current timestamp to compare against
     * @return bool True if the timer is ready to execute, false otherwise
     */
    public function isReady(float $currentTime): bool;

    /**
     * Executes the timer's callback function.
     *
     * This method should be called by the event loop when the timer
     * is ready to fire.
     */
    public function execute(): void;
}
