<?php

namespace Hibla\EventLoop\UV\Factories;

use Hibla\EventLoop\Managers\StreamManager;
use Hibla\EventLoop\UV\Handlers\UVSleepHandler;
use Hibla\EventLoop\UV\Handlers\UVWorkHandler;
use Hibla\EventLoop\UV\Managers\UVSocketManager;
use Hibla\EventLoop\UV\Managers\UVTimerManager;

/**
 * Factory for creating UV-specific components
 */
final class UVComponentFactory
{
    private static $uvLoop = null;

    public static function createTimerManager(): UVTimerManager
    {
        return new UVTimerManager(self::getUvLoop());
    }

    public static function createStreamManager(): StreamManager
    {
        return new StreamManager;
    }

    public static function createSocketManager(): UVSocketManager
    {
        return new UVSocketManager(self::getUvLoop());
    }

    public static function createWorkHandler(
        $timerManager,
        $httpRequestManager,
        $streamManager,
        $fiberManager,
        $tickHandler,
        $fileManager,
        $socketManager
    ): UVWorkHandler {
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

    public static function createSleepHandler(
        $timerManager,
        $fiberManager
    ): UVSleepHandler {
        return new UVSleepHandler(
            $timerManager,
            $fiberManager,
            self::getUvLoop()
        );
    }

    public static function getUvLoop()
    {
        if (self::$uvLoop === null) {
            self::$uvLoop = \uv_default_loop();
        }

        return self::$uvLoop;
    }

    public static function resetUvLoop(): void
    {
        self::$uvLoop = null;
    }
}
