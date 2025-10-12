<?php

use Hibla\EventLoop\EventLoop;

require_once __DIR__ . '/vendor/autoload.php';

echo "=== Mid-Flight Stream Cancellation Tests ===\n\n";

$testResults = [
    'passed' => 0,
    'failed' => 0,
];

function recordResult(bool $passed, string $message): void
{
    global $testResults;

    if ($passed) {
        $testResults['passed']++;
        echo "  ✓ $message\n";
    } else {
        $testResults['failed']++;
        echo "  ✗ $message\n";
    }
}

// Test 1: Cancel streaming write mid-flight
echo "Test 1: Cancel streaming WRITE mid-flight\n";
$testFile1 = __DIR__ . '/test_midstream_write.txt';

if (file_exists($testFile1)) {
    unlink($testFile1);
}

// Create a large data payload (1MB)
$largeData = str_repeat('ABCDEFGHIJ', 100000); // 1MB of data
$dataSize = strlen($largeData);
echo '  Data size: ' . number_format($dataSize) . " bytes\n";

$writeCallbackExecuted = false;
$operationId1 = EventLoop::getInstance()->addFileOperation(
    'write',
    $testFile1,
    $largeData,
    function ($error, $result) use (&$writeCallbackExecuted, $testFile1) {
        $writeCallbackExecuted = true;
        if ($error) {
            echo "  ℹ Write callback called with error: $error\n";
        } else {
            echo "  ✗ Write callback called with success (should be cancelled!)\n";
            echo "  ✗ Bytes written: $result\n";
        }
    },
    ['use_streaming' => true]
);

// Schedule cancellation to happen mid-stream
EventLoop::getInstance()->addTimer(0.001, function () use ($operationId1, $testFile1) {
    echo "  → Attempting to cancel mid-flight...\n";

    $cancelled = EventLoop::getInstance()->cancelFileOperation($operationId1);
    recordResult($cancelled, 'Cancellation call returned: ' . ($cancelled ? 'true' : 'false'));

    // Check if file exists at moment of cancellation
    $existsDuringCancel = file_exists($testFile1);
    if ($existsDuringCancel) {
        $sizeDuringCancel = filesize($testFile1);
        echo '  ℹ File exists at cancellation with size: ' . number_format($sizeDuringCancel) . " bytes\n";
    } else {
        echo "  ℹ File doesn't exist at cancellation moment\n";
    }
});

EventLoop::getInstance()->addTimer(0.1, function () use ($testFile1, &$writeCallbackExecuted, $dataSize) {
    echo "  → Checking final state after cancellation...\n";

    $finalExists = file_exists($testFile1);

    if ($finalExists) {
        $finalSize = filesize($testFile1);
        $isPartial = $finalSize < $dataSize;
        $isComplete = $finalSize === $dataSize;

        echo '  ℹ Final file size: ' . number_format($finalSize) . " bytes\n";

        if ($isComplete) {
            recordResult(false, 'File is complete (should be cancelled/partial)');
        } elseif ($isPartial) {
            recordResult(true, 'File is partial/incomplete (correct - cancelled mid-flight)');
        }

        recordResult(! $writeCallbackExecuted, 'Write callback NOT executed: ' . ($writeCallbackExecuted ? 'false' : 'true'));

        // Clean up
        unlink($testFile1);
    } else {
        recordResult(true, "File doesn't exist (cancelled before any write)");
        recordResult(! $writeCallbackExecuted, 'Write callback NOT executed: ' . ($writeCallbackExecuted ? 'false' : 'true'));
    }

    echo "\n";
});

// Test 2: Cancel streaming read mid-flight
echo "Test 2: Cancel streaming READ mid-flight\n";
$testFile2 = __DIR__ . '/test_midstream_read.txt';

// Create a large file to read (2MB)
$largeContent = str_repeat('LINE_' . str_repeat('X', 95) . "\n", 20000); // ~2MB
file_put_contents($testFile2, $largeContent);
$fileSize = filesize($testFile2);
echo '  File size: ' . number_format($fileSize) . " bytes\n";

$readCallbackExecuted = false;
$bytesReadBeforeCancel = 0;

$operationId2 = EventLoop::getInstance()->addFileOperation(
    'read',
    $testFile2,
    null,
    function ($error, $result) use (&$readCallbackExecuted, &$bytesReadBeforeCancel) {
        $readCallbackExecuted = true;
        if ($error) {
            echo "  ℹ Read callback called with error: $error\n";
        } else {
            $bytesRead = strlen($result);
            $bytesReadBeforeCancel = $bytesRead;
            echo "  ✗ Read callback called with success (should be cancelled!)\n";
            echo '  ✗ Bytes read: ' . number_format($bytesRead) . "\n";
        }
    },
    ['use_streaming' => true]
);

// Cancel mid-read
EventLoop::getInstance()->addTimer(0.001, function () use ($operationId2) {
    echo "  → Attempting to cancel mid-flight...\n";

    $cancelled = EventLoop::getInstance()->cancelFileOperation($operationId2);
    recordResult($cancelled, 'Cancellation call returned: ' . ($cancelled ? 'true' : 'false'));
});

EventLoop::getInstance()->addTimer(0.1, function () use ($testFile2, &$readCallbackExecuted, &$bytesReadBeforeCancel, $fileSize) {
    echo "  → Checking final state after cancellation...\n";

    if ($readCallbackExecuted) {
        $isPartial = $bytesReadBeforeCancel < $fileSize;
        if ($isPartial) {
            recordResult(true, 'Read was partial (correct - cancelled mid-flight)');
        } else {
            recordResult(false, 'Read was complete (should be cancelled)');
        }
    } else {
        recordResult(true, 'Read callback NOT executed (cancelled before completion)');
    }

    // Clean up
    unlink($testFile2);
    echo "\n";
});

// Test 3: Cancel streaming copy mid-flight
echo "Test 3: Cancel streaming COPY mid-flight\n";
$testSource3 = __DIR__ . '/test_midstream_copy_source.txt';
$testDest3 = __DIR__ . '/test_midstream_copy_dest.txt';

// Create large source file (1.5MB)
$sourceContent = str_repeat('COPY_DATA_', 150000); // ~1.5MB
file_put_contents($testSource3, $sourceContent);
$sourceSize = filesize($testSource3);
echo '  Source size: ' . number_format($sourceSize) . " bytes\n";

if (file_exists($testDest3)) {
    unlink($testDest3);
}

$copyCallbackExecuted = false;

$operationId3 = EventLoop::getInstance()->addFileOperation(
    'copy',
    $testSource3,
    $testDest3,
    function ($error, $result) use (&$copyCallbackExecuted) {
        $copyCallbackExecuted = true;
        if ($error) {
            echo "  ℹ Copy callback called with error: $error\n";
        } else {
            echo "  ✗ Copy callback called with success (should be cancelled!)\n";
        }
    },
    ['use_streaming' => true]
);

// Cancel mid-copy
EventLoop::getInstance()->addTimer(0.001, function () use ($operationId3, $testDest3) {
    echo "  → Attempting to cancel mid-flight...\n";

    $cancelled = EventLoop::getInstance()->cancelFileOperation($operationId3);
    recordResult($cancelled, 'Cancellation call returned: ' . ($cancelled ? 'true' : 'false'));

    if (file_exists($testDest3)) {
        $sizeDuringCancel = filesize($testDest3);
        echo '  ℹ Destination size at cancellation: ' . number_format($sizeDuringCancel) . " bytes\n";
    }
});

EventLoop::getInstance()->addTimer(0.1, function () use ($testSource3, $testDest3, &$copyCallbackExecuted, $sourceSize) {
    echo "  → Checking final state after cancellation...\n";

    if (file_exists($testDest3)) {
        $destSize = filesize($testDest3);
        $isPartial = $destSize < $sourceSize;
        $isComplete = $destSize === $sourceSize;

        echo '  ℹ Final destination size: ' . number_format($destSize) . " bytes\n";

        if ($isComplete) {
            recordResult(false, 'Copy is complete (should be cancelled/partial)');
        } elseif ($isPartial) {
            recordResult(true, 'Copy is partial (correct - cancelled mid-flight)');
        }

        unlink($testDest3);
    } else {
        recordResult(true, "Destination doesn't exist (cancelled before any copy)");
    }

    recordResult(! $copyCallbackExecuted, 'Copy callback NOT executed: ' . ($copyCallbackExecuted ? 'false' : 'true'));

    unlink($testSource3);
    echo "\n";
});

// Test 4: Cancel generator write mid-flight (using nextTick)
echo "Test 4: Cancel generator WRITE mid-flight\n";
$testFile4 = __DIR__ . '/test_midstream_generator.txt';

if (file_exists($testFile4)) {
    unlink($testFile4);
}

$chunksYielded = 0;

$generator4 = (function () use (&$chunksYielded) {
    for ($i = 0; $i < 1000; $i++) {
        $chunksYielded++;
        yield "CHUNK_$i:" . str_repeat('DATA', 250) . "\n"; // ~1KB per chunk
    }
})();

$genCallbackExecuted = false;

$operationId4 = EventLoop::getInstance()->addFileOperation(
    'write_generator',
    $testFile4,
    $generator4,
    function ($error, $result) use (&$genCallbackExecuted) {
        $genCallbackExecuted = true;
        if ($error) {
            echo "  ℹ Generator callback called with error: $error\n";
        } else {
            echo "  ✗ Generator callback called with success (should be cancelled!)\n";
            echo "  ✗ Bytes written: $result\n";
        }
    }
);

// Use nextTick to cancel on the very next iteration while generator is processing
EventLoop::getInstance()->nextTick(function () use ($operationId4) {
    echo "  → Attempting to cancel on next tick (mid-flight)...\n";

    $cancelled = EventLoop::getInstance()->cancelFileOperation($operationId4);
    recordResult($cancelled, 'Cancellation call returned: ' . ($cancelled ? 'true' : 'false'));
});

EventLoop::getInstance()->defer(function () use ($testFile4, &$genCallbackExecuted, &$chunksYielded) {
    echo "  → Checking final state after cancellation...\n";
    echo "  ℹ Chunks yielded: $chunksYielded\n";

    if (file_exists($testFile4)) {
        $finalSize = filesize($testFile4);
        echo '  ℹ Final file size: ' . number_format($finalSize) . " bytes\n";

        $expectedFullSize = 1000 * 1004; // ~1MB if complete
        $isPartial = $finalSize < $expectedFullSize;

        if ($isPartial || $chunksYielded < 1000) {
            recordResult(true, 'Generator write is partial or incomplete (correct - cancelled mid-flight)');
        } else {
            recordResult(false, 'Generator write is complete (should be cancelled)');
        }

        unlink($testFile4);
    } else {
        recordResult(true, "File doesn't exist (cancelled before any write)");
    }

    recordResult(! $genCallbackExecuted, 'Generator callback NOT executed: ' . ($genCallbackExecuted ? 'false' : 'true'));

    echo "\n";
});

// Test 5: Rapid cancel (cancel immediately after scheduling)
echo "Test 5: RAPID cancel (immediate cancellation)\n";
$testFile5 = __DIR__ . '/test_rapid_cancel.txt';

if (file_exists($testFile5)) {
    unlink($testFile5);
}

$rapidData = str_repeat('RAPID', 200000); // 1MB
$rapidCallbackExecuted = false;

$operationId5 = EventLoop::getInstance()->addFileOperation(
    'write',
    $testFile5,
    $rapidData,
    function ($error, $result) use (&$rapidCallbackExecuted) {
        $rapidCallbackExecuted = true;
        echo "  ✗ Rapid cancel callback executed (should NOT happen!)\n";
    },
    ['use_streaming' => true]
);

// Cancel IMMEDIATELY (same tick)
$rapidCancelled = EventLoop::getInstance()->cancelFileOperation($operationId5);
recordResult($rapidCancelled, 'Immediate cancellation returned: ' . ($rapidCancelled ? 'true' : 'false'));

EventLoop::getInstance()->addTimer(0.05, function () use ($testFile5, &$rapidCallbackExecuted) {
    echo "  → Checking final state...\n";

    $exists = file_exists($testFile5);
    recordResult(! $exists, "File doesn't exist: " . ($exists ? 'false' : 'true'));
    recordResult(! $rapidCallbackExecuted, 'Callback NOT executed: ' . ($rapidCallbackExecuted ? 'false' : 'true'));

    if ($exists) {
        echo '  ⚠ File size: ' . filesize($testFile5) . " bytes\n";
        unlink($testFile5);
    }

    echo "\n";
});

// Final report
EventLoop::getInstance()->addTimer(0.2, function () use (&$testResults) {
    echo "=== FINAL MID-FLIGHT CANCELLATION REPORT ===\n\n";

    $total = $testResults['passed'] + $testResults['failed'];
    $passRate = $total > 0 ? ($testResults['passed'] / $total) * 100 : 0;

    echo "Total Checks: $total\n";
    echo "Passed: {$testResults['passed']} ✓\n";
    echo "Failed: {$testResults['failed']} ✗\n";
    echo 'Pass Rate: ' . number_format($passRate, 1) . "%\n\n";

    if ($testResults['failed'] === 0) {
        echo "🎉 🎉 🎉 ALL MID-FLIGHT CANCELLATION TESTS PASSED! 🎉 🎉 🎉\n\n";
        echo "✓ Streaming writes can be cancelled mid-flight\n";
        echo "✓ Streaming reads can be cancelled mid-flight\n";
        echo "✓ Streaming copies can be cancelled mid-flight\n";
        echo "✓ Generator writes can be cancelled mid-generation\n";
        echo "✓ Immediate/rapid cancellation works\n";
        echo "✓ No callbacks execute for cancelled operations\n";
        echo "✓ Partial data is handled correctly\n\n";
        echo "🔒 MID-FLIGHT CANCELLATION IS FULLY FUNCTIONAL!\n";
    } else {
        echo "⚠ SOME TESTS FAILED - Details above\n";
        echo "Most likely timing issues with fast operations\n";
    }

    EventLoop::getInstance()->stop();
});

EventLoop::getInstance()->run();
