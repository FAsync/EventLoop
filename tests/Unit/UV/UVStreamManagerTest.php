<?php

use Hibla\EventLoop\UV\Detectors\UVDetector;
use Hibla\EventLoop\UV\Managers\UVStreamManager;
use Hibla\EventLoop\ValueObjects\StreamWatcher;

beforeEach(function () {
    if (!UVDetector::isUvAvailable()) {
        test()->markTestSkipped('UV extension not available');
    }
});

describe('UVStreamManager', function () {
    it('can create a UV stream manager', function () {
        $streamManager = new UVStreamManager();
        
        expect($streamManager)->toBeInstanceOf(UVStreamManager::class);
        expect($streamManager->hasWatchers())->toBeFalse();
    });

    it('can add a read stream watcher', function () {
        $streamManager = new UVStreamManager();
        [$readSocket, $writeSocket] = createTestSocketPair();
        $callbackExecuted = false;
        
        $watcherId = $streamManager->addStreamWatcher($readSocket, function ($stream, $type) use (&$callbackExecuted) {
            $callbackExecuted = true;
        }, StreamWatcher::TYPE_READ);
        
        expect($watcherId)->toBeString();
        expect($streamManager->hasWatchers())->toBeTrue();
        
        // Write data to trigger read callback
        fwrite($writeSocket, "test data\n");
        
        // Run UV loop to process the stream
        $loop = \uv_default_loop();
        $startTime = microtime(true);
        
        while (!$callbackExecuted && (microtime(true) - $startTime) < 1.0) {
            \uv_run($loop, \UV::RUN_NOWAIT);
            usleep(1000);
        }
        
        expect($callbackExecuted)->toBeTrue();
        
        fclose($readSocket);
        fclose($writeSocket);
    });

    it('can add a write stream watcher', function () {
        $streamManager = new UVStreamManager();
        [$readSocket, $writeSocket] = createTestSocketPair();
        $callbackExecuted = false;
        
        $watcherId = $streamManager->addStreamWatcher($writeSocket, function ($stream, $type) use (&$callbackExecuted) {
            $callbackExecuted = true;
        }, StreamWatcher::TYPE_WRITE);
        
        expect($watcherId)->toBeString();
        expect($streamManager->hasWatchers())->toBeTrue();
        
        // Run UV loop to process the stream (write should be immediately available)
        $loop = \uv_default_loop();
        $startTime = microtime(true);
        
        while (!$callbackExecuted && (microtime(true) - $startTime) < 1.0) {
            \uv_run($loop, \UV::RUN_NOWAIT);
            usleep(1000);
        }
        
        expect($callbackExecuted)->toBeTrue();
        
        fclose($readSocket);
        fclose($writeSocket);
    });

    it('can remove a stream watcher', function () {
        $streamManager = new UVStreamManager();
        [$readSocket, $writeSocket] = createTestSocketPair();
        
        $watcherId = $streamManager->addStreamWatcher($readSocket, function () {});
        
        expect($streamManager->hasWatchers())->toBeTrue();
        expect($streamManager->removeStreamWatcher($watcherId))->toBeTrue();
        expect($streamManager->hasWatchers())->toBeFalse();
        
        fclose($readSocket);
        fclose($writeSocket);
    });

    it('can clear all watchers', function () {
        $streamManager = new UVStreamManager();
        [$readSocket1, $writeSocket1] = createTestSocketPair();
        [$readSocket2, $writeSocket2] = createTestSocketPair();
        
        $streamManager->addStreamWatcher($readSocket1, function () {});
        $streamManager->addStreamWatcher($readSocket2, function () {});
        
        expect($streamManager->hasWatchers())->toBeTrue();
        
        $streamManager->clearAllWatchers();
        
        expect($streamManager->hasWatchers())->toBeFalse();
        
        fclose($readSocket1);
        fclose($writeSocket1);
        fclose($readSocket2);
        fclose($writeSocket2);
    });

    it('handles invalid stream resource gracefully', function () {
        $streamManager = new UVStreamManager();
        
        expect(function () use ($streamManager) {
            $streamManager->addStreamWatcher('not-a-resource', function () {});
        })->toThrow(\InvalidArgumentException::class, 'Invalid stream resource');
    });

    it('handles callback exceptions gracefully', function () {
        $streamManager = new UVStreamManager();
        [$readSocket, $writeSocket] = createTestSocketPair();
        $secondCallbackExecuted = false;
        
        // Add watcher that throws
        $streamManager->addStreamWatcher($readSocket, function () {
            throw new Exception('Stream callback error');
        });
        
        // Add second watcher to verify it still works
        $streamManager->addStreamWatcher($writeSocket, function () use (&$secondCallbackExecuted) {
            $secondCallbackExecuted = true;
        }, StreamWatcher::TYPE_WRITE);
        
        // Trigger both callbacks
        fwrite($writeSocket, "test\n");
        
        $loop = \uv_default_loop();
        $startTime = microtime(true);
        
        while (!$secondCallbackExecuted && (microtime(true) - $startTime) < 1.0) {
            \uv_run($loop, \UV::RUN_NOWAIT);
            usleep(1000);
        }
        
        expect($secondCallbackExecuted)->toBeTrue();
        
        fclose($readSocket);
        fclose($writeSocket);
    });
});