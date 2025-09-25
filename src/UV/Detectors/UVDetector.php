<?php

namespace Hibla\EventLoop\UV\Detectors;

/**
 * Detects availability of ext-uv and provides fallback mechanisms
 */
final class UVDetector
{
    private static ?bool $uvAvailable = null;

    public static function isUvAvailable(): bool
    {
        if (self::$uvAvailable === null) {
            self::$uvAvailable = extension_loaded('uv');
        }

        return self::$uvAvailable;
    }

    public static function requiresUv(): bool
    {
        return self::isUvAvailable() &&
               (isset($_ENV['FIBER_ASYNC_FORCE_UV']) && $_ENV['FIBER_ASYNC_FORCE_UV'] !== '' && $_ENV['FIBER_ASYNC_FORCE_UV'] !== '0') ||
               (isset($_SERVER['FIBER_ASYNC_FORCE_UV']) && $_SERVER['FIBER_ASYNC_FORCE_UV'] !== '' && $_SERVER['FIBER_ASYNC_FORCE_UV'] !== '0');
    }
}
