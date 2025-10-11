<?php

use Dotenv\Dotenv;
use Hibla\EventLoop\EventLoop;
use Rcalicdan\Defer\Defer;
use Rcalicdan\Defer\Handlers\SignalRegistryHandler;

$cwd = getcwd();
$currentDir = $cwd !== false ? $cwd : __DIR__;

$possibleRoots = [
    $currentDir,
    dirname(__DIR__, 5),
    dirname(__DIR__, 4),
    dirname(__DIR__, 3),
    dirname(__DIR__, 2),
    __DIR__,
];

$dotenv = null;
foreach ($possibleRoots as $path) {
    if (file_exists($path . '/.env')) {
        $dotenv = Dotenv::createImmutable($path);

        break;
    }
}

if ($dotenv === null) {
    $cwd = getcwd();
    $dotenv = Dotenv::createImmutable($cwd !== false ? $cwd : __DIR__);
}

$dotenv->safeLoad();

function setupGracefulShutdown(EventLoop $eventLoop): void
{
    $signalHandler = new SignalRegistryHandler(function () use ($eventLoop) {
        if ($eventLoop->isRunning()) {
            $eventLoop->stop();
        }
    });

    $signalHandler->register();

    register_shutdown_function(function () use ($eventLoop) {
        if ($eventLoop->isRunning()) {
            $eventLoop->stop();
        }
    });
}

$enableEventLoop = filter_var(
    getenv('ENABLE_GLOBAL_EVENT_LOOP') !== false
        ? getenv('ENABLE_GLOBAL_EVENT_LOOP')
        : ($_ENV['ENABLE_GLOBAL_EVENT_LOOP'] ?? 'true'),
    FILTER_VALIDATE_BOOLEAN
);

try {
    if ($enableEventLoop) {
        Defer::global(function () {
            $eventLoop = EventLoop::getInstance();
            setupGracefulShutdown($eventLoop);

            try {
                $eventLoop->run();
            } catch (Throwable $e) {
                $eventLoop->stop();
            }
        });
    }
} finally {
    EventLoop::reset();
}
