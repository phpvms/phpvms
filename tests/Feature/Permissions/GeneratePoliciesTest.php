<?php

declare(strict_types=1);

use App\Console\Commands\GeneratePolicies;
use Composer\Autoload\ClassLoader;
use Illuminate\Support\Facades\File;

/**
 * Invoke the protected modulePathForClass resolver on a fresh command.
 */
function resolveModulePath(string $class): ?string
{
    $command = new GeneratePolicies();
    $method = (new ReflectionMethod($command, 'modulePathForClass'));

    return $method->invoke($command, $class);
}

it('generates a thin policy for a resource model that lacks one', function (): void {
    $path = app_path('Policies/Filament/FlightBundlePolicy.php');
    $original = File::exists($path) ? File::get($path) : null;
    File::delete($path);

    try {
        $this->artisan('permission:generate-policies')->assertSuccessful();

        expect(File::exists($path))->toBeTrue();

        $content = File::get($path);
        expect($content)->toContain('extends BasePolicy');
        expect($content)->toContain("\$subject = 'flight-bundle'");
    } finally {
        if ($original !== null) {
            File::put($path, $original);
        }
    }
});

it('does not overwrite an existing policy without --force', function (): void {
    $path = app_path('Policies/Filament/UserPolicy.php');
    $before = File::get($path);

    $this->artisan('permission:generate-policies')->assertSuccessful();

    expect(File::get($path))->toBe($before);
});

it('regenerates a valid stub with --force', function (): void {
    $this->artisan('permission:generate-policies --force')->assertSuccessful();

    $content = File::get(app_path('Policies/Filament/AwardPolicy.php'));
    expect($content)->toContain('extends BasePolicy');
    expect($content)->toContain("\$subject = 'award'");
});

it('resolves a module policy path via the most specific PSR-4 prefix', function (): void {
    /** @var ClassLoader $loader */
    $loader = require base_path('vendor/autoload.php');

    // A module that maps its namespace to an app/ subdirectory must keep the
    // app/ segment; the generic Modules\ => modules fallback must not win.
    $loader->addPsr4('Modules\\Foo\\', base_path('modules/Foo/app'));

    try {
        $path = resolveModulePath('Modules\\Foo\\Policies\\Filament\\BarPolicy');

        expect($path)->toEndWith('modules/Foo/app/Policies/Filament/BarPolicy.php');
    } finally {
        $loader->setPsr4('Modules\\Foo\\', []);
    }
});
