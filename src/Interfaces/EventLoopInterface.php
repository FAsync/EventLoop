<?php

namespace Hibla\EventLoop\Interfaces;

use Fiber;

/**
 * Core event loop interface for managing asynchronous operations.
 *
 * The event loop is responsible for scheduling and executing asynchronous
 * operations including timers, HTTP requests, stream I/O, file operations,
 * socket operations, and fiber management.
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
     * Schedule a periodic timer that executes repeatedly at specified intervals.
     *
     * @param  float  $interval  Interval in seconds between executions
     * @param  callable  $callback  Function to execute on each interval
     * @param  int|null  $maxExecutions  Maximum number of executions (null for infinite)
     * @return string Unique identifier for the periodic timer
     */
    public function addPeriodicTimer(float $interval, callable $callback, ?int $maxExecutions = null): string;

    /**
     * Cancel a previously scheduled timer.
     *
     * @param  string  $timerId  The timer ID returned by addTimer() or addPeriodicTimer()
     * @return bool True if timer was cancelled, false if not found
     */
    public function cancelTimer(string $timerId): bool;

    /**
     * Check if the event loop has any active timers.
     *
     * @return bool True if there are active timers, false otherwise
     */
    public function hasTimers(): bool;

    /**
     * Schedule an asynchronous HTTP request.
     *
     * @param  string  $url  The URL to request
     * @param  array<int, mixed>  $options  cURL options for the request, using CURLOPT_* constants.
     * @param  callable  $callback  Function to execute when request completes
     * @return string A unique ID for the request
     */
    public function addHttpRequest(string $url, array $options, callable $callback): string;

    /**
     * Cancel a previously scheduled HTTP request.
     *
     * @param  string  $requestId  The request ID returned by addHttpRequest()
     * @return bool True if request was cancelled, false if not found
     */
    public function cancelHttpRequest(string $requestId): bool;

    /**
     * Add a stream watcher for I/O operations.
     *
     * @param  resource  $stream  The stream resource to watch
     * @param  callable  $callback  Function to execute when stream has data
     * @param  string  $type  Type of stream operation (read/write)
     * @return string Unique identifier for the stream watcher
     */
    public function addStreamWatcher($stream, callable $callback, string $type = 'read'): string;

    /**
     * Remove a stream watcher.
     *
     * @param  string  $watcherId  The watcher ID returned by addStreamWatcher()
     * @return bool True if watcher was removed, false if not found
     */
    public function removeStreamWatcher(string $watcherId): bool;

    /**
     * Schedule an asynchronous file operation.
     *
     * @param  string  $type  Type of operation (read, write, append, etc.)
     * @param  string  $path  File path
     * @param  mixed  $data  Data for write operations
     * @param  callable  $callback  Function to execute when operation completes
     * @param  array<string, mixed>  $options  Additional options for the operation
     * @return string Unique identifier for the file operation
     */
    public function addFileOperation(
        string $type,
        string $path,
        mixed $data,
        callable $callback,
        array $options = []
    ): string;

    /**
     * Cancel a file operation.
     *
     * @param  string  $operationId  The operation ID returned by addFileOperation()
     * @return bool True if operation was cancelled, false if not found
     */
    public function cancelFileOperation(string $operationId): bool;

    /**
     * Add a file watcher to monitor file changes.
     *
     * @param  string  $path  Path to watch
     * @param  callable  $callback  Function to execute when file changes
     * @param  array<string, mixed>  $options  Additional options for watching
     * @return string Unique identifier for the file watcher
     */
    public function addFileWatcher(string $path, callable $callback, array $options = []): string;

    /**
     * Remove a file watcher.
     *
     * @param  string  $watcherId  The watcher ID returned by addFileWatcher()
     * @return bool True if watcher was removed, false if not found
     */
    public function removeFileWatcher(string $watcherId): bool;

    /**
     * Add a fiber to be managed by the event loop.
     *
     * @param  Fiber<mixed, mixed, mixed, mixed>  $fiber  The fiber instance to add to the loop
     */
    public function addFiber(Fiber $fiber): void;

    /**
     * Schedules a callback to run on the next tick of the event loop.
     *
     * Next tick callbacks have higher priority than timers and I/O operations.
     *
     * @param  callable  $callback  The callback to execute on next tick
     */
    public function nextTick(callable $callback): void;

    /**
     * Defers execution of a callback until the current call stack is empty.
     *
     * Similar to nextTick but with lower priority.
     *
     * @param  callable  $callback  The callback to defer
     */
    public function defer(callable $callback): void;

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
     * Force immediate stop of the event loop.
     *
     * This bypasses graceful shutdown and immediately clears all work.
     */
    public function forceStop(): void;

    /**
     * Check if the event loop is currently running.
     *
     * @return bool True if the loop is running, false otherwise
     */
    public function isRunning(): bool;

    /**
     * Checks if the event loop has no pending operations.
     *
     * @return bool True if the loop is idle (no pending operations), false otherwise
     */
    public function isIdle(): bool;

    /**
     * Get current iteration count (useful for debugging/monitoring).
     *
     * @return int The number of loop iterations completed
     */
    public function getIterationCount(): int;

    /**
     * Get the socket manager instance.
     *
     * @return object The socket manager instance
     */
    public function getSocketManager(): object;

    /**
     * Get the timer manager instance.
     *
     * @return object The timer manager instance
     */
    public function getTimerManager(): object;

    /**
     * Get the singleton instance of the event loop.
     *
     * @return static The singleton event loop instance
     */
    public static function getInstance(): self;

    /**
     * Reset the singleton instance (primarily for testing).
     */
    public static function reset(): void;
}
