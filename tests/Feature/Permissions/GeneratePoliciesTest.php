<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

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
