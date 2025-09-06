<?php

namespace Fasync\EventLoop\Interfaces;

/**
 * Core event loop interface for managing asynchronous operations.
 *
 * The event loop is responsible for scheduling and executing asynchronous
 * operations including timers, HTTP requests, stream I/O, and fiber management.
 */
interface EventLoopInterface
{
    /**
     * Schedules a callback to be executed after a delay.
     *
     * @param  float  $delay  Delay in seconds before execution
     * @param  callable  $callback  The callback to execute
     * @return string Unique timer ID that can be used to cancel the timer
     */
    public function addTimer(float $delay, callable $callback): string;

    /**
     * Schedule an asynchronous HTTP request.
     *
     * @param  string  $url  The URL to request
     * @param  array<int, mixed>  $options  cURL options for the request, using CURLOPT_* constants.
     * @param  callable  $callback  Function to execute when request completes
     */
    public function addHttpRequest(string $url, array $options, callable $callback): string;

    /**
     * Add a fiber to be managed by the event loop.
     *
     * @param  \Fiber<mixed, mixed, mixed, mixed>  $fiber  The fiber instance to add to the loop
     */
    public function addFiber(\Fiber $fiber): void;

    /**
     * Schedules a callback to run on the next tick of the event loop.
     *
     * Next tick callbacks have higher priority than timers and I/O operations.
     *
     * @param  callable  $callback  The callback to execute on next tick
     */
    public function nextTick(callable $callback): void;

    /**
     * Starts the event loop and continues until stopped or no more operations.
     *
     * This method blocks until the event loop is explicitly stopped or
     * there are no more pending operations.
     */
    public function run(): void;

    /**
     * Stops the event loop from running.
     *
     * This will cause the run() method to return after completing
     * the current iteration.
     */
    public function stop(): void;

    /**
     * Defers execution of a callback until the current call stack is empty.
     *
     * Similar to nextTick but with lower priority.
     *
     * @param  callable  $callback  The callback to defer
     */
    public function defer(callable $callback): void;

    /**
     * Checks if the event loop has no pending operations.
     *
     * @return bool True if the loop is idle (no pending operations), false otherwise
     */
    public function isIdle(): bool;
}
