<?php

declare(strict_types=1);

use App\Addons\AddonRegistry;
use App\Addons\Services\AddonDiscoveryService;
use App\Models\Addon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    Addon::query()->delete();

    $this->modules = sys_get_temp_dir().'/bundled-addon-'.uniqid();
    File::ensureDirectoryExists($this->modules);
    Config::set('addons.paths.base', $this->modules);
});

afterEach(function (): void {
    File::deleteDirectory($this->modules);
});

/**
 * Drop a valid addon on disk, optionally flagged bundled in its module.json.
 */
function placeBundledAddonOnDisk(string $base, string $name, bool $bundled): void
{
    $dir = $base.'/'.strtolower($name);
    File::ensureDirectoryExists($dir);
    File::put($dir.'/module.json', json_encode([
        'name'      => $name,
        'providers' => [],
        'bundled'   => $bundled,
    ]));
    File::put($dir.'/composer.json', json_encode(['autoload' => ['psr-4' => ['Modules\\'.$name.'\\' => '']]]));
}

it('discovers a bundled on-disk addon as enabled and bundled', function (): void {
    placeBundledAddonOnDisk($this->modules, 'CoreMod', bundled: true);

    app(AddonDiscoveryService::class)->discoverNewAddons();

    $addon = Addon::query()->where('name', 'CoreMod')->first();

    expect($addon)->not->toBeNull()
        ->and($addon->isEnabled())->toBeTrue()
        ->and($addon->isBundled())->toBeTrue();
});

it('discovers a normal on-disk addon as disabled and not bundled', function (): void {
    placeBundledAddonOnDisk($this->modules, 'PlainMod', bundled: false);

    app(AddonDiscoveryService::class)->discoverNewAddons();

    $addon = Addon::query()->where('name', 'PlainMod')->first();

    expect($addon)->not->toBeNull()
        ->and($addon->isEnabled())->toBeFalse()
        ->and($addon->isBundled())->toBeFalse();
});

it('refuses to disable a bundled addon', function (): void {
    $addon = Addon::factory()->create(['name' => 'CoreMod', 'bundled' => true, 'enabled' => true]);

    expect(fn (): mixed => app(AddonRegistry::class)->disable($addon->getName()))
        ->toThrow(RuntimeException::class);

    expect(Addon::query()->find($addon->id)->enabled)->toBeTrue();
});

it('refuses to delete a bundled addon', function (): void {
    $addon = Addon::factory()->create(['name' => 'CoreMod', 'bundled' => true]);

    expect(fn (): mixed => app(AddonRegistry::class)->delete($addon->getName()))
        ->toThrow(RuntimeException::class);

    expect(Addon::query()->find($addon->id))->not->toBeNull();
});

it('still disables and deletes a non-bundled addon', function (): void {
    $addon = Addon::factory()->create(['name' => 'PlainMod', 'bundled' => false, 'enabled' => true]);

    app(AddonRegistry::class)->disable($addon->getName());
    expect(Addon::query()->find($addon->id)->enabled)->toBeFalse();

    app(AddonRegistry::class)->delete($addon->getName());
    expect(Addon::query()->find($addon->id))->toBeNull();
});
