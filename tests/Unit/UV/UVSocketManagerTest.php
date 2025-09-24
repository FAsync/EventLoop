<?php

use Hibla\EventLoop\UV\Detectors\UVDetector;
use Hibla\EventLoop\UV\Managers\UVSocketManager;

beforeEach(function () {
    if (!UVDetector::isUvAvailable()) {
        test()->markTestSkipped('UV extension not available');
    }
});

describe('UVSocketManager', function () {
    it('can create a UV socket manager', function () {
        $socketManager = new UVSocketManager();
        
        expect($socketManager)->toBeInstanceOf(UVSocketManager::class);
        expect($socketManager->hasWatchers())->toBeFalse();
    });

    it('can add a read watcher', function () {
        $socketManager = new UVSocketManager();
        [$readSocket, $writeSocket] = createTestSocketPair();
        $callbackExecuted = false;
        
        $socketManager->addReadWatcher($readSocket, function () use (&$callbackExecuted) {
            $callbackExecuted = true;
        });
        
        expect($socketManager->hasWatchers())->toBeTrue();
        
        fwrite($writeSocket, "test data\n");
        
        // Run UV loop to process the socket
        $loop = \uv_default_loop();
        $startTime = microtime(true);
        
        while (!$callbackExecuted && (microtime(true) - $startTime) < 1.0) {
            \uv_run($loop, \UV::RUN_NOWAIT);
            usleep(1000);
        }
        
        expect($callbackExecuted)->toBeTrue();
        expect($socketManager->processSockets())->toBeFalse(); 
        
        fclose($readSocket);
        fclose($writeSocket);
    });

    it('can add a write watcher', function () {
        $socketManager = new UVSocketManager();
        [$readSocket, $writeSocket] = createTestSocketPair();
        $callbackExecuted = false;
        
        $socketManager->addWriteWatcher($writeSocket, function () use (&$callbackExecuted) {
            $callbackExecuted = true;
        });
        
        expect($socketManager->hasWatchers())->toBeTrue();
      
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

    it('can remove watchers', function () {
        $socketManager = new UVSocketManager();
        [$readSocket, $writeSocket] = createTestSocketPair();
        
        $socketManager->addReadWatcher($readSocket, function () {});
        $socketManager->addWriteWatcher($writeSocket, function () {});
        
        expect($socketManager->hasWatchers())->toBeTrue();
        
        $socketManager->removeReadWatcher($readSocket);
        $socketManager->removeWriteWatcher($writeSocket);
        
        expect($socketManager->hasWatchers())->toBeFalse();
        
        fclose($readSocket);
        fclose($writeSocket);
    });

    it('can clear all watchers', function () {
        $socketManager = new UVSocketManager();
        [$readSocket1, $writeSocket1] = createTestSocketPair();
        [$readSocket2, $writeSocket2] = createTestSocketPair();
        
        $socketManager->addReadWatcher($readSocket1, function () {});
        $socketManager->addReadWatcher($readSocket2, function () {});
        
        expect($socketManager->hasWatchers())->toBeTrue();
        
        $socketManager->clearAllWatchers();
        
        expect($socketManager->hasWatchers())->toBeFalse();
        
        fclose($readSocket1);
        fclose($writeSocket1);
        fclose($readSocket2);
        fclose($writeSocket2);
    });

    it('can clear all watchers for specific socket', function () {
        $socketManager = new UVSocketManager();
        [$readSocket1, $writeSocket1] = createTestSocketPair();
        [$readSocket2, $writeSocket2] = createTestSocketPair();
        
        $socketManager->addReadWatcher($readSocket1, function () {});
        $socketManager->addReadWatcher($readSocket2, function () {});
        
        expect($socketManager->hasWatchers())->toBeTrue();
        
        $socketManager->clearAllWatchersForSocket($readSocket1);
        
        expect($socketManager->hasWatchers())->toBeTrue(); // Still has readSocket2
        
        fclose($readSocket1);
        fclose($writeSocket1);
        fclose($readSocket2);
        fclose($writeSocket2);
    });

    it('handles non-resource gracefully', function () {
        $socketManager = new UVSocketManager();
        
        // Should fall back to parent implementation
        $socketManager->addReadWatcher('not-a-resource', function () {});
        $socketManager->addWriteWatcher('not-a-resource', function () {});
        
        // Should not crash and fall back to parent
        expect($socketManager)->toBeInstanceOf(UVSocketManager::class);
    });

    it('handles callback exceptions gracefully', function () {
        $socketManager = new UVSocketManager();
        [$readSocket, $writeSocket] = createTestSocketPair();
        $secondCallbackExecuted = false;
        
        // Add watcher that throws
        $socketManager->addReadWatcher($readSocket, function () {
            throw new Exception('Socket callback error');
        });
        
        // Add second watcher to verify it still works
        [$readSocket2, $writeSocket2] = createTestSocketPair();
        $socketManager->addWriteWatcher($writeSocket2, function () use (&$secondCallbackExecuted) {
            $secondCallbackExecuted = true;
        });
        
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
        fclose($readSocket2);
        fclose($writeSocket2);
    });
});