<?php

namespace Fasync\EventLoop\Factories;

use Fasync\EventLoop\Detectors\UvDetector;
use Fasync\EventLoop\Handlers\SleepHandler;
use Fasync\EventLoop\Handlers\UvWorkHandler;
use Fasync\EventLoop\Handlers\WorkHandler;
use Fasync\EventLoop\Handlers\UvSleepHandler;
use Fasync\EventLoop\Managers\SocketManager;
use Fasync\EventLoop\Managers\StreamManager;
use Fasync\EventLoop\Managers\TimerManager;
use Fasync\EventLoop\Managers\Uv\UvSocketManager;
use Fasync\EventLoop\Managers\Uv\UvStreamManager;
use Fasync\EventLoop\Managers\Uv\UvTimerManager;

/**
 * Factory for creating UV-aware or fallback components
 */
final class EventLoopComponentFactory
{
    private static $uvLoop = null;

    public static function createTimerManager(): TimerManager
    {
        if (UvDetector::isUvAvailable()) {
            return new UvTimerManager(self::getUvLoop());
        }
        
        return new TimerManager();
    }

    public static function createStreamManager(): StreamManager
    {
        if (UvDetector::isUvAvailable()) {
            return new UvStreamManager(self::getUvLoop());
        }
        
        return new StreamManager();
    }

    public static function createSocketManager(): SocketManager
    {
        if (UvDetector::isUvAvailable()) {
            return new UvSocketManager(self::getUvLoop());
        }
        
        return new SocketManager();
    }

    public static function createWorkHandler(
        $timerManager,
        $httpRequestManager,
        $streamManager,
        $fiberManager,
        $tickHandler,
        $fileManager,
        $socketManager
    ): WorkHandler {
        if (UvDetector::isUvAvailable()) {
            return new UvWorkHandler(
                self::getUvLoop(),
                $timerManager,
                $httpRequestManager,
                $streamManager,
                $fiberManager,
                $tickHandler,
                $fileManager,
                $socketManager
            );
        }

        return new WorkHandler(
            $timerManager,
            $httpRequestManager,
            $streamManager,
            $fiberManager,
            $tickHandler,
            $fileManager,
            $socketManager
        );
    }

    public static function createSleepHandler(
        $timerManager,
        $fiberManager
    ): SleepHandler {
        if (UvDetector::isUvAvailable()) {
            return new UvSleepHandler(
                $timerManager,
                $fiberManager,
                self::getUvLoop()
            );
        }

        return new SleepHandler($timerManager, $fiberManager);
    }

    private static function getUvLoop()
    {
        if (self::$uvLoop === null && UvDetector::isUvAvailable()) {
            self::$uvLoop = \uv_default_loop();
        }
        
        return self::$uvLoop;
    }

    public static function resetUvLoop(): void
    {
        self::$uvLoop = null;
    }
}