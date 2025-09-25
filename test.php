<?php

use Hibla\EventLoop\Loop;

require 'vendor/autoload.php';

Loop::addPeriodicTimer(1, function () {
    echo 'tick' . PHP_EOL;
});