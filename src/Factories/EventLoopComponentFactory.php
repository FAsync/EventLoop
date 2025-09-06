<?php

namespace Hibla\EventLoop\Factories;

use Hibla\EventLoop\Detectors\UvDetector;
use Hibla\EventLoop\Handlers\SleepHandler;
use Hibla\EventLoop\Handlers\UvWorkHandler;
use Hibla\EventLoop\Handlers\WorkHandler;
use Hibla\EventLoop\Handlers\UvSleepHandler;
use Hibla\EventLoop\Managers\SocketManager;
use Hibla\EventLoop\Managers\StreamManager;
use Hibla\EventLoop\Managers\TimerManager;
use Hibla\EventLoop\Managers\Uv\UvSocketManager;
use Hibla\EventLoop\Managers\Uv\UvStreamManager;
use Hibla\EventLoop\Managers\Uv\UvTimerManager;

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