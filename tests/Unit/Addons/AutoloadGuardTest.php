<?php

declare(strict_types=1);

use App\Addons\Support\AutoloadGuard;
use App\Exceptions\AutoloadModeException;
use Composer\Autoload\ClassLoader;

it('isClassMapAuthoritative() returns true when loader is in classmap-authoritative mode', function (): void {
    $loader = new ClassLoader();
    $loader->setClassMapAuthoritative(true);

    $guard = new AutoloadGuard();

    expect($guard->isClassMapAuthoritative($loader))->toBeTrue();
});

it('isClassMapAuthoritative() returns false when loader is not in classmap-authoritative mode', function (): void {
    $loader = new ClassLoader();
    $loader->setClassMapAuthoritative(false);

    $guard = new AutoloadGuard();

    expect($guard->isClassMapAuthoritative($loader))->toBeFalse();
});

it('assertRuntimeAutoloadSupported() throws AutoloadModeException for authoritative loader', function (): void {
    $loader = new ClassLoader();
    $loader->setClassMapAuthoritative(true);

    $guard = new AutoloadGuard();

    expect(fn () => $guard->assertRuntimeAutoloadSupported($loader))
        ->toThrow(AutoloadModeException::class);
});

it('assertRuntimeAutoloadSupported() does not throw for non-authoritative loader', function (): void {
    $loader = new ClassLoader();
    $loader->setClassMapAuthoritative(false);

    $guard = new AutoloadGuard();

    // Should not throw — no assertion needed beyond not throwing
    $guard->assertRuntimeAutoloadSupported($loader);

    expect(true)->toBeTrue();
});

it('AutoloadModeException message mentions classmap-authoritative and composer dump-autoload', function (): void {
    $exception = new AutoloadModeException();

    expect($exception->getMessage())
        ->toContain('classmap-authoritative')
        ->and($exception->getMessage())->toContain('composer dump-autoload');
});

it('isClassMapAuthoritative() with no argument returns a bool without error', function (): void {
    $guard = new AutoloadGuard();

    $result = $guard->isClassMapAuthoritative();

    expect($result)->toBeBool();
});
