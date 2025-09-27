<?php

use Dotenv\Dotenv;
use Hibla\EventLoop\EventLoop;
use Rcalicdan\Defer\Defer;
use Rcalicdan\Defer\Handlers\SignalRegistryHandler;

$dotenvPaths = [
    __DIR__ . '/../',
    __DIR__ . '/../../',
    __DIR__ . '/../../../',
    __DIR__ . '/../../../../',
    __DIR__ . '/',
];

$dotenv = null;
foreach ($dotenvPaths as $path) {
    if (file_exists($path . '.env') || file_exists($path . '.env.example')) {
        $dotenv = Dotenv::createImmutable($path);

        break;
    }
}

$dotenv = $dotenv !== null ? $dotenv : Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

function setupGracefulShutdown(EventLoop $eventLoop): void
{
    $signalHandler = new SignalRegistryHandler(function () use ($eventLoop) {
        if ($eventLoop->isRunning()) {
            $eventLoop->stop();
            $eventLoop->reset();
        }
    });

    $signalHandler->register();

    register_shutdown_function(function () use ($eventLoop) {
        if ($eventLoop->isRunning()) {
            $eventLoop->stop();
            $eventLoop->reset();
        }
    });
}

$enableEventLoop = filter_var(
    getenv('ENABLE_GLOBAL_EVENT_LOOP') !== false
        ? getenv('ENABLE_GLOBAL_EVENT_LOOP')
        : ($_ENV['ENABLE_GLOBAL_EVENT_LOOP'] ?? 'true'),
    FILTER_VALIDATE_BOOLEAN
);

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
