<?php

use Hibla\EventLoop\EventLoop;
use Hibla\EventLoop\UV\Detectors\UVDetector;
use Hibla\EventLoop\ValueObjects\StreamWatcher;

beforeEach(function () {
    if (!UVDetector::isUvAvailable()) {
        test()->markTestSkipped('UV extension not available');
    }
});

describe('EventLoop with UV Integration', function () {
    it('detects UV availability correctly', function () {
        $loop = EventLoop::getInstance();
        
        expect($loop->isUsingUv())->toBeTrue();
        expect($loop->getTimerManager())->toBeInstanceOf(\Hibla\EventLoop\UV\Managers\UVTimerManager::class);
        expect($loop->getSocketManager())->toBeInstanceOf(\Hibla\EventLoop\UV\Managers\UVSocketManager::class);
    });

    it('can execute timers through event loop', function () {
        $loop = EventLoop::getInstance();
        $executed = false;
        
        $timerId = $loop->addTimer(0.01, function () use (&$executed, $loop) {
            $executed = true;
            $loop->stop();
        });
        
        expect($timerId)->toBeString();
        expect($loop->hasTimers())->toBeTrue();
        
        $loop->run();
        
        expect($executed)->toBeTrue();
    });

    it('can execute periodic timers through event loop', function () {
        $loop = EventLoop::getInstance();
        $executionCount = 0;
        $maxExecutions = 3;
        
        $loop->addPeriodicTimer(0.01, function () use (&$executionCount, $loop, $maxExecutions) {
            $executionCount++;
            if ($executionCount >= $maxExecutions) {
                $loop->stop();
            }
        }, $maxExecutions);
        
        expect($loop->hasTimers())->toBeTrue();
        
        $loop->run();
        
        expect($executionCount)->toBe($maxExecutions);
    });

    it('can handle stream watchers through event loop', function () {
        $loop = EventLoop::getInstance();
        [$readSocket, $writeSocket] = createTestSocketPair();
        $callbackExecuted = false;
        
        $watcherId = $loop->addStreamWatcher($readSocket, function ($stream, $type) use (&$callbackExecuted, $loop) {
            $data = fread($stream, 1024);
            expect($data)->toBe("test data\n");
            $callbackExecuted = true;
            $loop->stop();
        }, StreamWatcher::TYPE_READ);
        
        expect($watcherId)->toBeString();
        
        // Schedule writing data after a short delay
        $loop->addTimer(0.01, function () use ($writeSocket) {
            fwrite($writeSocket, "test data\n");
        });
        
        $loop->run();
        
        expect($callbackExecuted)->toBeTrue();
        
        $loop->removeStreamWatcher($watcherId);
        fclose($readSocket);
        fclose($writeSocket);
    });

    it('can cancel timers through event loop', function () {
        $loop = EventLoop::getInstance();
        $executed = false;
        
        $timerId = $loop->addTimer(0.1, function () use (&$executed) {
            $executed = true;
        });
        
        expect($loop->cancelTimer($timerId))->toBeTrue();
        
        // Run loop for a short time to ensure timer doesn't execute
        $loop->addTimer(0.05, function () use ($loop) {
            $loop->stop();
        });
        
        $loop->run();
        
        expect($executed)->toBeFalse();
    });

    it('can handle mixed UV and non-UV operations', function () {
        $loop = EventLoop::getInstance();
        $timerExecuted = false;
        $nextTickExecuted = false;
        $deferredExecuted = false;
        
        // UV timer
        $loop->addTimer(0.02, function () use (&$timerExecuted) {
            $timerExecuted = true;
        });
        
        // Next tick (non-UV)
        $loop->nextTick(function () use (&$nextTickExecuted) {
            $nextTickExecuted = true;
        });
        
        // Deferred (non-UV)
        $loop->defer(function () use (&$deferredExecuted) {
            $deferredExecuted = true;
        });
        
        // Stop after all should be executed
        $loop->addTimer(0.05, function () use ($loop) {
            $loop->stop();
        });
        
        $loop->run();
        
        expect($nextTickExecuted)->toBeTrue();
        expect($deferredExecuted)->toBeTrue();
        expect($timerExecuted)->toBeTrue();
    });

    it('handles graceful shutdown with UV timers', function () {
        $loop = EventLoop::getInstance();
        $executionCount = 0;
        
        $loop->addPeriodicTimer(0.01, function () use (&$executionCount) {
            $executionCount++;
        });
        
        // Stop the loop after a short time
        $loop->addTimer(0.05, function () use ($loop) {
            $loop->stop();
        });
        
        $loop->run();
        
        expect($executionCount)->toBeGreaterThan(0);
        expect($loop->isRunning())->toBeFalse();
    });

    it('can force stop the loop', function () {
        $loop = EventLoop::getInstance();
        $executed = false;
        
        $loop->addTimer(0.1, function () use (&$executed) {
            $executed = true;
        });
        
        // Force stop immediately
        $loop->nextTick(function () use ($loop) {
            $loop->forceStop();
        });
        
        $loop->run();
        
        expect($executed)->toBeFalse();
        expect($loop->isRunning())->toBeFalse();
    });

    it('tracks iteration count correctly', function () {
        $loop = EventLoop::getInstance();
        $initialCount = $loop->getIterationCount();
        
        $loop->addTimer(0.01, function () use ($loop) {
            $loop->stop();
        });
        
        $loop->run();
        
        expect($loop->getIterationCount())->toBeGreaterThan($initialCount);
    });
});