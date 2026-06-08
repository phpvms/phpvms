<?php

declare(strict_types=1);

use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Support\BootCache;
use App\Models\Addon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    // The addons migration seeds bundled module rows; clear them so each test
    // starts with a known-empty state.
    Addon::query()->delete();

    $this->base = sys_get_temp_dir().'/rebuild-'.uniqid();
    $this->addonDir = $this->base.'/Demo';
    File::ensureDirectoryExists($this->addonDir);
    File::put($this->addonDir.'/module.json', json_encode(['name' => 'Demo', 'providers' => []]));
    File::put($this->addonDir.'/composer.json', json_encode(['autoload' => ['psr-4' => ['Modules\\Demo\\' => '']]]));
    Config::set('addons.paths.base', $this->base);

    app(BootCache::class)->delete();
});

afterEach(function (): void {
    app(BootCache::class)->delete();
    File::deleteDirectory($this->base);
});

it('rebuildCache() writes enabled addons to the boot cache', function (): void {
    Addon::factory()->create(['name' => 'Demo', 'path' => $this->addonDir, 'enabled' => true]);

    app(AddonDiscoveryService::class)->rebuildCache();

    $names = app(BootCache::class)->enabled()->map(fn ($e): string => $e->name)->all();
    expect($names)->toContain('Demo');
});

it('rebuildCache() omits disabled addons', function (): void {
    Addon::factory()->create(['name' => 'Demo', 'path' => $this->addonDir, 'enabled' => false]);

    app(AddonDiscoveryService::class)->rebuildCache();

    expect(app(BootCache::class)->enabled())->toHaveCount(0);
});
