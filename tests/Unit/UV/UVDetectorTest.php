<?php

use Hibla\EventLoop\UV\Detectors\UVDetector;

describe('UVDetector', function () {
    it('detects UV extension availability', function () {
        $isAvailable = UVDetector::isUvAvailable();
        
        expect($isAvailable)->toBeBool();
        expect($isAvailable)->toBe(extension_loaded('uv'));
    });

    it('caches UV availability check', function () {
        // Call multiple times to test caching
        $first = UVDetector::isUvAvailable();
        $second = UVDetector::isUvAvailable();
        $third = UVDetector::isUvAvailable();
        
        expect($first)->toBe($second);
        expect($second)->toBe($third);
        expect($first)->toBe(extension_loaded('uv'));
    });

    it('checks if UV is required via environment variables', function () {
        $isRequired = UVDetector::requiresUv();
        
        expect($isRequired)->toBeBool();
        
        $_ENV['FIBER_ASYNC_FORCE_UV'] = '1';
        if (UVDetector::isUvAvailable()) {
            expect(UVDetector::requiresUv())->toBeTrue();
        }
        
        unset($_ENV['FIBER_ASYNC_FORCE_UV']);
        
        $_SERVER['FIBER_ASYNC_FORCE_UV'] = '1';
        if (UVDetector::isUvAvailable()) {
            expect(UVDetector::requiresUv())->toBeTrue();
        }
        
        unset($_SERVER['FIBER_ASYNC_FORCE_UV']);
    });
});