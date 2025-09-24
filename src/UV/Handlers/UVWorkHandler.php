<?php

namespace Hibla\EventLoop\UV\Handlers;

use Hibla\EventLoop\Handlers\WorkHandler;
use Hibla\EventLoop\Handlers\TickHandler;
use Hibla\EventLoop\Managers\FiberManager;
use Hibla\EventLoop\Managers\FileManager;
use Hibla\EventLoop\Managers\HttpRequestManager;

/**
 * UV-aware work handler that integrates with libuv event loop
 */
final class UVWorkHandler extends WorkHandler
{
    private $uvLoop;
    private const UV_RUN_ONCE = 1;

    public function __construct(
        $uvLoop,
        $timerManager,
        HttpRequestManager $httpRequestManager,
        $streamManager,
        FiberManager $fiberManager,
        TickHandler $tickHandler,
        FileManager $fileManager,
        $socketManager,
    ) {
        $this->uvLoop = $uvLoop;

        parent::__construct(
            $timerManager,
            $httpRequestManager,
            $streamManager,
            $fiberManager,
            $tickHandler,
            $fileManager,
            $socketManager
        );
    }

    public function processWork(): bool
    {
        $workDone = false;

        if ($this->tickHandler->processNextTickCallbacks()) {
            $workDone = true;
        }

        if ($this->fiberManager->processFibers()) {
            $workDone = true;
        }

        if ($this->httpRequestManager->processRequests()) {
            $workDone = true;
        }

        if ($this->fileManager->processFileOperations()) {
            $workDone = true;
        }
        
        if ($this->streamManager->hasWatchers()) {
            $this->streamManager->processStreams();
            $workDone = true;
        }

        if ($this->runUvLoop()) {
            $workDone = true;
        }

        if ($this->tickHandler->processDeferredCallbacks()) {
            $workDone = true;
        }

        return $workDone;
    }

    private function runUvLoop(): bool
    {
        try {
            $result = \uv_run($this->uvLoop, self::UV_RUN_ONCE);
            return $result > 0;
        } catch (\Error | \Exception $e) {
            error_log('UV loop error: ' . $e->getMessage());
            return false;
        }
    }
}