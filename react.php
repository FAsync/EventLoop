<?php
// test_reactphp.php

require_once 'vendor/autoload.php';

use React\EventLoop\Loop;

$timerCount = 100000;
$completed = 0;

echo "Testing ReactPHP with {$timerCount} timers...\n";

$startMemory = memory_get_usage();
$startTime = microtime(true);

$loop = Loop::get();

for ($i = 0; $i < $timerCount; $i++) {
    $loop->addTimer(1, function() use (&$completed, $loop, $timerCount) {
        $completed++;
        
        // Stop when all timers complete
        if ($completed === $timerCount) {
            $loop->stop();
        }
    });
}

// Run the loop
$loop->run();

$executionTime = microtime(true) - $startTime;
$memoryUsed = memory_get_usage() - $startMemory;
$peakMemory = memory_get_peak_usage();

echo "\n=== ReactPHP Results ===\n";
echo "Timer count: {$timerCount}\n";
echo "Completed: {$completed}\n";
echo "Execution time: " . round($executionTime, 4) . " seconds\n";
echo "Memory used: " . number_format($memoryUsed) . " bytes\n";
echo "Peak memory: " . number_format($peakMemory) . " bytes\n";
echo "Timers per second: " . round($timerCount / $executionTime, 2) . "\n";
echo "Memory per timer: " . round($memoryUsed / $timerCount, 2) . " bytes\n";