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
})->skip(sampleAddonMissing(...), 'No sample addon on disk; it is not tracked in this repo.');

it('records the Sample module helpers.php in the boot cache files list', function (): void {
    $this->artisan('phpvms:addons-prime')->assertSuccessful();

    $sample = app(BootCache::class)->all()
        ->firstWhere(fn ($entry): bool => $entry->namespace === 'Modules\\Sample');

    // Assert against the addon's own discovered path, not a literal directory
    // name: the engine resolves autoload.files relative to wherever the addon
    // was found, so pinning this to "modules/Sample" would only be testing that
    // the checkout happens to use that folder name.
    $endsWithHelper = collect($sample?->files ?? [])
        ->contains(fn (string $path): bool => str_starts_with($path, (string) $sample?->path)
            && str_ends_with(str_replace('\\', '/', $path), '/helpers.php'));

    expect($sample)->not->toBeNull()
        ->and($endsWithHelper)->toBeTrue('Sample boot-cache row must record helpers.php in files');
})->skip(sampleAddonMissing(...), 'No sample addon on disk; it is not tracked in this repo.');
