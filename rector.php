<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use RectorLaravel\Rector\Class_\AppendsPropertyToAppendsAttributeRector;
use RectorLaravel\Rector\Class_\FillablePropertyToFillableAttributeRector;
use RectorLaravel\Rector\Class_\GuardedPropertyToGuardedAttributeRector;
use RectorLaravel\Rector\Class_\HiddenPropertyToHiddenAttributeRector;
use RectorLaravel\Rector\Class_\TablePropertyToTableAttributeRector;
use RectorLaravel\Rector\Class_\TouchesPropertyToTouchesAttributeRector;
use RectorLaravel\Set\LaravelSetProvider;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/config',
        __DIR__.'/routes',
        __DIR__.'/resources',
        __DIR__.'/tests',
    ])
    ->withSetProviders(LaravelSetProvider::class)
    ->withComposerBased(laravel: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        earlyReturn: true,
    )
    ->withPhpSets()
    ->withSkip([
        RemoveUselessVarTagRector::class => [
            __DIR__.'/app/Http/Resources/SimBriefResource.php',
        ],
        // This test names module classes as plain strings on purpose -- it only
        // exercises namespace parsing, so no addon has to be installed. Rewriting
        // them to ::class breaks a fresh checkout, where modules/.gitignore means
        // the addon directory is absent and PHPStan reports class.notFound.
        StringClassNameToClassConstantRector::class => [
            __DIR__.'/tests/Feature/Permissions/PermissionRegistryTest.php',
        ],
        FillablePropertyToFillableAttributeRector::class,
        TablePropertyToTableAttributeRector::class,
        GuardedPropertyToGuardedAttributeRector::class,
        AppendsPropertyToAppendsAttributeRector::class,
        TouchesPropertyToTouchesAttributeRector::class,
        HiddenPropertyToHiddenAttributeRector::class,
    ])
    ->withImportNames(true, true);
