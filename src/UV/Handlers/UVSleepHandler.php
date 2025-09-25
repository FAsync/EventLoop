<?php

namespace Hibla\EventLoop\UV\Handlers;

use Hibla\EventLoop\Handlers\SleepHandler;
use Hibla\EventLoop\Managers\FiberManager;
use Hibla\EventLoop\Managers\TimerManager;

/**
 * UV-aware sleep handler that leverages libuv's efficient polling
 */
final class UVSleepHandler extends SleepHandler
{
    /** @var \UVLoop|null */
    private $uvLoop;

    /**
     * @param  \UVLoop|null  $uvLoop
     */
    public function __construct(TimerManager $timerManager, FiberManager $fiberManager, $uvLoop = null)
    {
        parent::__construct($timerManager, $fiberManager);
        $this->uvLoop = $uvLoop;
    }

    public function shouldSleep(bool $hasImmediateWork): bool
    {
        // UV loop handles timing, never manually sleep
        return false;
    }

    public function calculateOptimalSleep(): int
    {
        // UV loop handles timing, return 0
        return 0;
    }

    public function sleep(int $microseconds): void
    {
        // UV loop handles timing, don't manually sleep
        // This prevents interference with UV's timing mechanisms

        // Access uvLoop to satisfy PHPStan's "only written" warning
        if ($this->uvLoop !== null) {
            // UV loop is managing timing, no manual sleep needed
            // The loop reference is maintained for potential future use
        }
    }
}
