<?php

use Hibla\EventLoop\EventLoop;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)->in('Feature', 'Integration');
pest()->extend(Tests\TestCase::class)->in('Unit');

beforeEach(function () {
    EventLoop::reset();
});

afterEach(function () {
    EventLoop::reset();
});

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeResource', function () {
    return $this->toBeResource();
});

expect()->extend('toBeValidTimestamp', function () {
    return $this->toBeFloat()
        ->toBeGreaterThan(0)
        ->toBeLessThan(time() + 3600); 
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can define your own functions.
|
*/

/**
 * Create a temporary stream resource for testing
 */
function createTestStream()
{
    $stream = fopen('php://temp', 'r+');
    if ($stream === false) {
        throw new RuntimeException('Failed to create test stream');
    }
    return $stream;
}

/**
 * Create a test socket pair for testing
 */
function createTestSocketPair(): array
{
    $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    if ($sockets === false) {
        throw new RuntimeException('Failed to create socket pair');
    }
    return $sockets;
}

/**
 * Run event loop for a specific duration
 */
function runLoopFor(float $seconds): void
{
    $loop = EventLoop::getInstance();
    
    $loop->addTimer($seconds, function () use ($loop) {
        $loop->stop();
    });
    
    $loop->run();
}

/**
 * Wait for condition with timeout
 */
function waitFor(callable $condition, float $timeout = 1.0): bool
{
    $startTime = microtime(true);
    while (microtime(true) - $startTime < $timeout) {
        if ($condition()) {
            return true;
        }
        usleep(1000); 
    }
    return false;
}