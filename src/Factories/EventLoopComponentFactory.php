<?php

namespace Hibla\EventLoop\Factories;

use Hibla\EventLoop\Detectors\UVDetector;
use Hibla\EventLoop\Handlers\SleepHandler;
use Hibla\EventLoop\Handlers\UVSleepHandler;
use Hibla\EventLoop\Handlers\UVWorkHandler;
use Hibla\EventLoop\Handlers\WorkHandler;
use Hibla\EventLoop\Managers\SocketManager;
use Hibla\EventLoop\Managers\StreamManager;
use Hibla\EventLoop\Managers\TimerManager;
use Hibla\EventLoop\Managers\UV\UVSocketManager;
use Hibla\EventLoop\Managers\UV\UVStreamManager;
use Hibla\EventLoop\Managers\UV\UVTimerManager;

/**
 * Factory for creating UV-aware or fallback components
 */
final class EventLoopComponentFactory
{
    private static $uvLoop = null;

    public static function createTimerManager(): TimerManager
    {
        if (UVDetector::isUvAvailable()) {
            return new UVTimerManager(self::getUvLoop());
        }

        return new TimerManager;
    }

    public static function createStreamManager(): StreamManager
    {
        if (UvDetector::isUvAvailable()) {
            return new UVStreamManager(self::getUvLoop());
        }

        return new StreamManager;
    }

    public static function createSocketManager(): SocketManager
    {
        if (UVDetector::isUvAvailable()) {
            return new UVSocketManager(self::getUvLoop());
        }

        return new SocketManager;
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
        if (UVDetector::isUvAvailable()) {
            return new UVWorkHandler(
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
        if (UVDetector::isUvAvailable()) {
            return new UVSleepHandler(
                $timerManager,
                $fiberManager,
                self::getUvLoop()
            );
        }

        return new SleepHandler($timerManager, $fiberManager);
    }

    private static function getUvLoop()
    {
        if (self::$uvLoop === null && UVDetector::isUvAvailable()) {
            self::$uvLoop = \uv_default_loop();
        }

        return self::$uvLoop;
    }

    public static function resetUvLoop(): void
    {
        self::$uvLoop = null;
    }
}
