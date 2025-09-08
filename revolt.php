<?php
// test_revolt.php

require_once 'vendor/autoload.php';

use Revolt\EventLoop;

$timerCount = 100000;
$completed = 0;

echo "Testing Revolt with {$timerCount} timers...\n";

$startMemory = memory_get_usage();
$startTime = microtime(true);

for ($i = 0; $i < $timerCount; $i++) {
    EventLoop::delay(1, function() use (&$completed) {
        $completed++;
    });
}

EventLoop::run();

$executionTime = microtime(true) - $startTime;
$memoryUsed = memory_get_usage() - $startMemory;
$peakMemory = memory_get_peak_usage();

echo "\n=== Revolt Results ===\n";
echo "Timer count: {$timerCount}\n";
echo "Completed: {$completed}\n";
echo "Execution time: " . round($executionTime, 4) . " seconds\n";
echo "Memory used: " . number_format($memoryUsed) . " bytes\n";
echo "Peak memory: " . number_format($peakMemory) . " bytes\n";
echo "Timers per second: " . round($timerCount / $executionTime, 2) . "\n";
echo "Memory per timer: " . round($memoryUsed / $timerCount, 2) . " bytes\n";