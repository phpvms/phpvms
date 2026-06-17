<?php

declare(strict_types=1);

use App\Addons\AddonAutoLoader;
use App\Addons\Support\BootCache;

beforeEach(function (): void {
    app(BootCache::class)->delete();
});

afterEach(function (): void {
    app(BootCache::class)->delete();
});

it('loads the Sample module helpers.php so its global helper is callable', function (): void {
    // Prime the boot cache so the bundled Sample module is discovered and its
    // composer.json autoload.files entry is recorded.
    $this->artisan('phpvms:addons-prime')->assertSuccessful();

    // Register enabled addons: PSR-4 + autoload.files + providers.
    app(AddonAutoLoader::class)->register(app());

    expect(function_exists('sample_module_greeting'))->toBeTrue()
        ->and(sample_module_greeting())->toBe('Hello from the Sample module!');
});

it('records the Sample module helpers.php in the boot cache files list', function (): void {
    $this->artisan('phpvms:addons-prime')->assertSuccessful();

    $sample = app(BootCache::class)->all()
        ->firstWhere(fn ($entry): bool => $entry->namespace === 'Modules\\Sample');

    $endsWithHelper = collect($sample?->files ?? [])
        ->contains(fn (string $path): bool => str_ends_with($path, 'modules/Sample/helpers.php'));

    expect($sample)->not->toBeNull()
        ->and($endsWithHelper)->toBeTrue('Sample boot-cache row must record helpers.php in files');
});
