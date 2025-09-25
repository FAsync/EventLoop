<?php

namespace Hibla\EventLoop\Factories;

use Hibla\EventLoop\Handlers\SleepHandler;
use Hibla\EventLoop\Handlers\TickHandler;
use Hibla\EventLoop\Handlers\WorkHandler;
use Hibla\EventLoop\Managers\FiberManager;
use Hibla\EventLoop\Managers\FileManager;
use Hibla\EventLoop\Managers\HttpRequestManager;
use Hibla\EventLoop\Managers\SocketManager;
use Hibla\EventLoop\Managers\StreamManager;
use Hibla\EventLoop\Managers\TimerManager;
use Hibla\EventLoop\UV\Detectors\UVDetector;
use Hibla\EventLoop\UV\Factories\UVComponentFactory;
use Hibla\EventLoop\UV\Managers\UVSocketManager;
use Hibla\EventLoop\UV\Managers\UVTimerManager;

/**
 * Factory for creating UV-aware or fallback components
 */
final class EventLoopComponentFactory
{
    public static function createTimerManager(): TimerManager
    {
        if (UVDetector::isUvAvailable()) {
            return UVComponentFactory::createTimerManager();
        }

        return new TimerManager;
    }

    public static function createStreamManager(): StreamManager
    {
        if (UVDetector::isUvAvailable()) {
            return UVComponentFactory::createStreamManager();
        }

        return new StreamManager;
    }

    public static function createSocketManager(): SocketManager
    {
        if (UVDetector::isUvAvailable()) {
            return UVComponentFactory::createSocketManager();
        }

        return new SocketManager;
    }

    public static function createWorkHandler(
        TimerManager|UVTimerManager $timerManager,
        HttpRequestManager $httpRequestManager,
        StreamManager $streamManager,
        FiberManager $fiberManager,
        TickHandler $tickHandler,
        FileManager $fileManager,
        SocketManager|UVSocketManager $socketManager
    ): WorkHandler {
        if (UVDetector::isUvAvailable()) {
            return UVComponentFactory::createWorkHandler(
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
        TimerManager|UVTimerManager $timerManager,
        FiberManager $fiberManager
    ): SleepHandler {
        if (UVDetector::isUvAvailable()) {
            return UVComponentFactory::createSleepHandler($timerManager, $fiberManager);
        }

        return new SleepHandler($timerManager, $fiberManager);
    }

    public static function resetUvLoop(): void
    {
        if (UVDetector::isUvAvailable()) {
            UVComponentFactory::resetUvLoop();
        }
    }
}
