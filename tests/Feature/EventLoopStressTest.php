<?php

use Hibla\EventLoop\EventLoop;

describe('EventLoop Stress Tests', function () {
    it('handles many timers efficiently', function () {
        $loop = EventLoop::getInstance();
        $executed = 0;

        $timerCount = getenv('CI') ? 100 : 1000;

        for ($i = 0; $i < $timerCount; $i++) {
            $loop->addTimer(0.001 + $i * 0.0001, function () use (&$executed) {
                $executed++;
            });
        }

        $timeout = getenv('CI') ? 2.0 : 0.5;
        $loop->addTimer($timeout, function () use ($loop) {
            $loop->stop();
        });

        $startTime = microtime(true);
        $loop->run();
        $duration = microtime(true) - $startTime;

        expect($executed)->toBe($timerCount);
        expect($duration)->toBeLessThan(5.0); 
    });

    it('handles adding many fibers without crashing', function () {
        $loop = EventLoop::getInstance();
        $fiberCount = 1000;

        for ($i = 0; $i < $fiberCount; $i++) {
            $fiber = new Fiber(fn() => "done");
            $loop->addFiber($fiber);
        }

        $loop->addTimer(0.01, function () use ($loop) {
            $loop->stop();
        });

        $startTime = microtime(true);
        $loop->run();
        $duration = microtime(true) - $startTime;

        expect($duration)->toBeLessThan(2.0);
    });

    it('handles rapid nextTick scheduling', function () {
        $loop = EventLoop::getInstance();
        $executed = 0;
        $tickCount = 500;

        for ($i = 0; $i < $tickCount; $i++) {
            $loop->nextTick(function () use (&$executed) {
                $executed++;
            });
        }

        $loop->addTimer(0.001, function () use ($loop) {
            $loop->stop();
        });

        $loop->run();

        expect($executed)->toBe($tickCount);
    });

    it('maintains performance with mixed workload', function () {
        $loop = EventLoop::getInstance();
        $counters = [
            'timers' => 0,
            'periodic' => 0,
            'ticks' => 0,
            'deferred' => 0
        ];

        for ($i = 0; $i < 50; $i++) {
            $loop->addTimer(0.001 + $i * 0.0001, function () use (&$counters) {
                $counters['timers']++;
            });

            $loop->nextTick(function () use (&$counters) {
                $counters['ticks']++;
            });

            $loop->defer(function () use (&$counters) {
                $counters['deferred']++;
            });
        }

        for ($i = 0; $i < 10; $i++) {
            $loop->addPeriodicTimer(0.001, function () use (&$counters) {
                $counters['periodic']++;
            }, 3);
        }

        $loop->addTimer(0.1, function () use ($loop) {
            $loop->stop();
        });

        $startTime = microtime(true);
        $loop->run();
        $duration = microtime(true) - $startTime;

        expect($counters['timers'])->toBe(50);
        expect($counters['ticks'])->toBe(50);
        expect($counters['deferred'])->toBe(50);
        expect($counters['periodic'])->toBe(30); // 10 timers * 3 executions
        expect($duration)->toBeLessThan(1.0);
    });

    it('handles memory efficiently with many operations', function () {
        $loop = EventLoop::getInstance();
        $initialMemory = memory_get_usage();

        for ($i = 0; $i < 1000; $i++) {
            $loop->addTimer(0.001, function () {
                // Empty callback
            });
        }

        runLoopFor(0.1);

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        expect($memoryIncrease)->toBeLessThan(1024 * 1024);
    });
});
