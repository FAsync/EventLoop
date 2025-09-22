<?php

use Hibla\EventLoop\EventLoop;
use Rcalicdan\Defer\Defer;
use Dotenv\Dotenv;

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

$dotenv = $dotenv ?: Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

function setupGracefulShutdown($eventLoop): void
{
    register_shutdown_function(function () use ($eventLoop) {
        if ($eventLoop->isRunning()) {
            $eventLoop->stop();
        }
    });

    if (function_exists('pcntl_signal')) {
        $signalHandler = function () use ($eventLoop) {
            $eventLoop->stop();
        };

        pcntl_signal(SIGINT, $signalHandler);
        pcntl_signal(SIGTERM, $signalHandler);
        pcntl_async_signals(true);
    } elseif (PHP_OS_FAMILY === 'Windows' && function_exists('sapi_windows_set_ctrl_handler')) {
        sapi_windows_set_ctrl_handler(function ($event) use ($eventLoop) {
            if (in_array($event, [PHP_WINDOWS_EVENT_CTRL_C, PHP_WINDOWS_EVENT_CTRL_BREAK])) {
                $eventLoop->stop();
                return true;
            }
            return false;
        });
    }
}

$enableEventLoop = filter_var($_ENV['ENABLE_GLOBAL_EVENT_LOOP'] ?? getenv('ENABLE_GLOBAL_EVENT_LOOP') ?: 'true', FILTER_VALIDATE_BOOLEAN);

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
