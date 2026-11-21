<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\ArrowFunction\ArrowFunctionDelegatingCallToFirstClassCallableRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use RectorLaravel\Rector\Class_\AppendsPropertyToAppendsAttributeRector;
use RectorLaravel\Rector\Class_\FillablePropertyToFillableAttributeRector;
use RectorLaravel\Rector\Class_\GuardedPropertyToGuardedAttributeRector;
use RectorLaravel\Rector\Class_\HiddenPropertyToHiddenAttributeRector;
use RectorLaravel\Rector\Class_\TablePropertyToTableAttributeRector;
use RectorLaravel\Rector\Class_\TouchesPropertyToTouchesAttributeRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/config',
        __DIR__.'/routes',
        __DIR__.'/resources',
        __DIR__.'/tests',
    ])->withSetProviders()
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
            // 7.x themes name the old enums as strings; the aliases only exist at runtime.
            __DIR__.'/tests/Feature/LegacyEnumAliasesTest.php',
        ],
        // A 7.x API shim: add-ons still call it, so it stays even though it does nothing.
        RemoveEmptyClassMethodRector::class            => [__DIR__.'/app/Support/LegacyModuleLinks.php'],
        RemoveUnusedPublicMethodParameterRector::class => [__DIR__.'/app/Support/LegacyModuleLinks.php'],
        // Pest binds dataset closures to the test-case instance; a first-class
        // callable of a static method is a static closure and cannot be bound
        // ("Cannot bind an instance to a static closure"), so datasets must
        // stay as arrow functions.
        ArrowFunctionDelegatingCallToFirstClassCallableRector::class => [
            __DIR__.'/tests',
        ],
        FillablePropertyToFillableAttributeRector::class,
        TablePropertyToTableAttributeRector::class,
        GuardedPropertyToGuardedAttributeRector::class,
        AppendsPropertyToAppendsAttributeRector::class,
        TouchesPropertyToTouchesAttributeRector::class,
        HiddenPropertyToHiddenAttributeRector::class,
    ])
    ->withImportNames(true, true);
