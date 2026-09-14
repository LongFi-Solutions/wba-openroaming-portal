<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Property\RemoveDefaultValueFromAssignedPropertyRector;
use Rector\Exception\Configuration\InvalidConfigurationException;

try {
    return RectorConfig::configure()
        ->withPaths([
            __DIR__ . '/assets',
            __DIR__ . '/config',
            __DIR__ . '/public',
            __DIR__ . '/src',
            __DIR__ . '/tests',
        ])
        // uncomment to reach your current PHP version
        ->withPhpSets(php84: true)
        ->withComposerBased(twig: true, doctrine: true, phpunit: true, symfony: true)
        ->withPreparedSets(deadCode: true, codeQuality: true)
        ->withTypeCoverageLevel(0)
        ->withSkip([
            RemoveDefaultValueFromAssignedPropertyRector::class, // ignore because of PHPUnit
        ]);
} catch (InvalidConfigurationException $e) {
    exit($e->getMessage());
}
