<?php

namespace Hibla\EventLoop\UV\Factories;

use Hibla\EventLoop\Handlers\TickHandler;
use Hibla\EventLoop\Managers\FiberManager;
use Hibla\EventLoop\Managers\FileManager;
use Hibla\EventLoop\Managers\HttpRequestManager;
use Hibla\EventLoop\Managers\StreamManager;
use Hibla\EventLoop\Managers\TimerManager;
use Hibla\EventLoop\UV\Handlers\UVSleepHandler;
use Hibla\EventLoop\UV\Handlers\UVWorkHandler;
use Hibla\EventLoop\UV\Managers\UVSocketManager;
use Hibla\EventLoop\UV\Managers\UVTimerManager;

/**
 * Factory for creating UV-specific components
 */
final class UVComponentFactory
{
    /** @var mixed */
    private static $uvLoop = null;

    public static function createTimerManager(): UVTimerManager
    {
        /** @phpstan-ignore-next-line */
        return new UVTimerManager(self::getUvLoop());
    }

    public static function createStreamManager(): StreamManager
    {
        return new StreamManager;
    }

    public static function createSocketManager(): UVSocketManager
    {
        /** @phpstan-ignore-next-line */
        return new UVSocketManager(self::getUvLoop());
    }

    public static function createWorkHandler(
        UVTimerManager $timerManager,
        HttpRequestManager $httpRequestManager,
        StreamManager $streamManager,
        FiberManager $fiberManager,
        TickHandler $tickHandler,
        FileManager $fileManager,
        UVSocketManager $socketManager
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
        TimerManager $timerManager,
        FiberManager $fiberManager
    ): UVSleepHandler {
        return new UVSleepHandler(
            $timerManager,
            $fiberManager,
            self::getUvLoop()
        );
    }

    /**
     * @return mixed
     */
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
