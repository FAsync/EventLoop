<?php

use Hibla\EventLoop\UV\Detectors\UVDetector;
use Hibla\EventLoop\UV\Managers\UVTimerManager;

beforeEach(function () {
    if (! UVDetector::isUvAvailable()) {
        test()->markTestSkipped('UV extension not available');
    }
});

describe('UVTimerManager', function () {
    it('can create a UV timer manager', function () {
        $timerManager = new UVTimerManager();

        expect($timerManager)->toBeInstanceOf(UVTimerManager::class);
        expect($timerManager->hasTimers())->toBeFalse();
    });

    it('can add and execute a single timer', function () {
        $timerManager = new UVTimerManager();
        $executed = false;

        $timerId = $timerManager->addTimer(0.01, function () use (&$executed) {
            $executed = true;
        });

        expect($timerId)->toBeString();
        expect($timerManager->hasTimer($timerId))->toBeTrue();
        expect($timerManager->hasTimers())->toBeTrue();

        // Run UV loop to process the timer
        $loop = \uv_default_loop();
        $startTime = microtime(true);

        while (! $executed && (microtime(true) - $startTime) < 1.0) {
            \uv_run($loop, UV::RUN_NOWAIT);
            usleep(1000);
        }

        expect($executed)->toBeTrue();
        expect($timerManager->hasTimer($timerId))->toBeFalse();
    });

    it('can add and execute a periodic timer', function () {
        $timerManager = new UVTimerManager();
        $executionCount = 0;
        $maxExecutions = 3;

        $timerId = $timerManager->addPeriodicTimer(0.01, function () use (&$executionCount) {
            $executionCount++;
        }, $maxExecutions);

        expect($timerId)->toBeString();
        expect($timerManager->hasTimer($timerId))->toBeTrue();

        // Run UV loop to process the periodic timer
        $loop = \uv_default_loop();
        $startTime = microtime(true);

        while ($executionCount < $maxExecutions && (microtime(true) - $startTime) < 2.0) {
            \uv_run($loop, UV::RUN_NOWAIT);
            usleep(1000);
        }

        expect($executionCount)->toBe($maxExecutions);
        expect($timerManager->hasTimer($timerId))->toBeFalse();
    });

    it('can cancel a timer before execution', function () {
        $timerManager = new UVTimerManager();
        $executed = false;

        $timerId = $timerManager->addTimer(0.1, function () use (&$executed) {
            $executed = true;
        });

        expect($timerManager->cancelTimer($timerId))->toBeTrue();
        expect($timerManager->hasTimer($timerId))->toBeFalse();

        // Wait to ensure timer doesn't execute
        $loop = \uv_default_loop();
        $startTime = microtime(true);

        while ((microtime(true) - $startTime) < 0.2) {
            \uv_run($loop, UV::RUN_NOWAIT);
            usleep(1000);
        }

        expect($executed)->toBeFalse();
    });

    it('can get timer statistics', function () {
        $timerManager = new UVTimerManager();

        $timer1 = $timerManager->addTimer(0.1, function () {});
        $timer2 = $timerManager->addPeriodicTimer(0.05, function () {}, 2);

        $stats = $timerManager->getTimerStats();

        expect($stats)->toHaveKeys([
            'uv_timers',
            'uv_regular_timers',
            'uv_periodic_timers',
            'total_timers',
        ]);

        expect($stats['uv_timers'])->toBe(2);
        expect($stats['uv_regular_timers'])->toBe(1);
        expect($stats['uv_periodic_timers'])->toBe(1);
    });

    it('handles callback exceptions gracefully', function () {
        $timerManager = new UVTimerManager();
        $secondExecuted = false;

        $timerManager->addTimer(0.01, function () {
            throw new Exception('Timer error');
        });

        $timerManager->addTimer(0.02, function () use (&$secondExecuted) {
            $secondExecuted = true;
        });

        $loop = \uv_default_loop();
        $startTime = microtime(true);

        while (! $secondExecuted && (microtime(true) - $startTime) < 1.0) {
            \uv_run($loop, UV::RUN_NOWAIT);
            usleep(1000);
        }

        expect($secondExecuted)->toBeTrue();
    });
});
