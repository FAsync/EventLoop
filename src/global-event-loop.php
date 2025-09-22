<?php

use Hibla\EventLoop\EventLoop;
use Rcalicdan\Defer\Defer;

$enableEventLoop = filter_var(getenv('ENABLE_EVENT_LOOP') ?: 'true', FILTER_VALIDATE_BOOLEAN);

if ($enableEventLoop) {
    Defer::global(function () {
        EventLoop::getInstance()->run();
    });
}