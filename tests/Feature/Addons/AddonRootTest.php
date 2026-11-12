<?php

declare(strict_types=1);

use App\Addons\AddonDeleteOutcome;
use App\Addons\AddonRegistry;
use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Sources\ZipSource;
use App\Addons\Support\AddonRoots;
use App\Addons\Support\BundledAddonLinker;
use App\Exceptions\AddonInstallException;
use App\Models\Addon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * The addon root: `addons.paths.root` is where the addon manager installs.
 * Bundled addons live in `addons.paths.bundled` and are symlinked into the
 * root. Discovery scans both, skipping the bundled names in the bundled source.
 *
 * Both are pointed at temp directories here. tests/Pest.php already redirects
 * the root away from the working copy for every test; this suite additionally
 * takes over the bundled source so it can model bundled addons.
 */
beforeEach(function (): void {
    Addon::query()->delete();

    $this->work = sys_get_temp_dir().'/addon-roots-'.uniqid('', true);
    $this->bundled = $this->work.'/bundled';
    $this->root = $this->work.'/addons';

    File::ensureDirectoryExists($this->bundled);
    File::ensureDirectoryExists($this->root);

    // Discovery stores realpath()-resolved paths, and on macOS the system temp
    // dir is itself a symlink (/var/folders -> /private/var/folders), so the
    // resolved form is what path assertions below have to compare against.
    $this->rootReal = realpath($this->root);

    Config::set('addons.paths.root', $this->root);
    Config::set('addons.paths.bundled', $this->bundled);
    Config::set('addons.bundled', []);
});

afterEach(function (): void {
    File::deleteDirectory($this->work);
});

/**
 * Drop a valid addon directory on disk under $root. No DB row — discovery is
 * what these tests are measuring.
 */
function placeAddonInRoot(string $root, string $name, ?string $registryId = null, ?string $version = null): string
{
    $dir = $root.'/'.strtolower($name);
    File::ensureDirectoryExists($dir);

    $manifest = [
        'name'        => $name,
        'registry_id' => $registryId ?? 'acme/'.strtolower($name),
        'providers'   => [],
    ];

    // Omitted entirely when null, so the unversioned cases exercise a manifest
    // with no `version` key rather than one holding an empty string.
    if ($version !== null) {
        $manifest['version'] = $version;
    }

    File::put($dir.'/addon.json', json_encode(addonManifest($manifest)));
    File::put($dir.'/composer.json', json_encode([
        'autoload' => ['psr-4' => ['Modules\\'.$name.'\\' => 'app/']],
    ]));

    return $dir;
}

/**
 * A zip source for an addon named $name, as the registry would serve it.
 */
function addonZip(string $workDir, string $name, string $registryId, ?string $version = null): string
{
    $path = $workDir.'/'.strtolower($name).'-'.($version ?? 'none').'.zip';

    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('addon.json', json_encode(addonManifest(array_filter([
        'name'        => $name,
        'registry_id' => $registryId,
        'version'     => $version,
        'providers'   => [],
    ], static fn (string|array|null $value): bool => $value !== null))));
    $zip->addFromString('composer.json', json_encode([
        'autoload' => ['psr-4' => ['Modules\\'.$name.'\\' => '']],
    ]));
    $zip->close();

    return $path;
}

/**
 * Ship $name as a bundled addon: its files in the bundled source, linked into
 * the root the way the release links them. Returns the bundled directory.
 */
function placeBundledAddon(string $name, string $registryId, string $version): string
{
    Config::set('addons.bundled', [...AddonRoots::bundled(), strtolower($name)]);
    $dir = placeAddonInRoot(AddonRoots::bundledSource(), $name, $registryId, $version);
    File::ensureDirectoryExists($dir.'/resources/views');
    File::put($dir.'/resources/views/keep.blade.php', 'keep me');

    app(BundledAddonLinker::class)->link();

    return $dir;
}

// ── Discovery ───────────────────────────────────────────────────────────────

it('invalidates the boot cache when an addon appears in the root', function (): void {
    placeAddonInRoot($this->root, 'Shipped');
    $service = app(AddonDiscoveryService::class);
    $service->discoverNewAddons();
    $service->rebuildCache();

    expect($service->primeIfNeeded())->toBeFalse();

    placeAddonInRoot($this->root, 'Added');

    expect($service->primeIfNeeded())->toBeTrue();
});

it('discovers an addon in modules/, and a bundled one only through its link', function (): void {
    $handPlaced = placeAddonInRoot($this->bundled, 'HandPlaced');
    placeBundledAddon('Shipped', 'acme/shipped', '1.0.0');
    Log::spy();

    app(AddonDiscoveryService::class)->discoverNewAddons();

    Log::shouldNotHaveReceived('warning', [Mockery::on(fn (string $message): bool => str_contains($message, 'twice'))]);
    expect(Addon::query()->where('registry_id', 'acme/handplaced')->value('path'))->toBe(realpath($handPlaced))
        ->and(Addon::query()->where('registry_id', 'acme/shipped')->count())->toBe(1)
        ->and(AddonRoots::isLinked((string) Addon::query()->where('registry_id', 'acme/shipped')->value('path')))->toBeTrue();
});

it('loads the copy in the root when modules/ has the same addon', function (): void {
    placeAddonInRoot($this->bundled, 'Twin', 'acme/twin', '1.0.0');
    $installed = placeAddonInRoot($this->root, 'Twin', 'acme/twin', '1.1.0');
    Log::spy();

    app(AddonDiscoveryService::class)->discoverNewAddons();

    Log::shouldNotHaveReceived('warning', [Mockery::on(fn (string $message): bool => str_contains($message, 'twice'))]);
    expect(Addon::query()->where('registry_id', 'acme/twin')->sole()->path)->toBe(realpath($installed));
});

it('does not re-enable an addon the operator disabled', function (): void {
    // Reconciling an existing row must not touch `enabled` (D-12).
    placeAddonInRoot($this->root, 'Twin', 'acme/twin', '1.0.0');
    app(AddonDiscoveryService::class)->discoverNewAddons();
    Addon::query()->where('registry_id', 'acme/twin')->update(['enabled' => false]);

    placeAddonInRoot($this->root, 'Twin', 'acme/twin', '1.1.0');
    app(AddonDiscoveryService::class)->discoverNewAddons();

    expect(Addon::query()->where('registry_id', 'acme/twin')->value('enabled'))->toBeFalsy();
});

// ── Install ─────────────────────────────────────────────────────────────────

it('install() rejects an addon whose registry_id already has a row', function (): void {
    // Placed under a directory name that does NOT match the safeName install()
    // would pick ('acme-demo') — as a bundled link is named after its modules/
    // directory. A directory-name comparison would miss this; registry_id
    // catches it.
    placeAddonInRoot($this->root, 'Demo', 'acme/demo');
    app(AddonDiscoveryService::class)->discoverNewAddons();

    $zip = addonZip($this->work, 'Demo', 'acme/demo');

    expect(fn () => app(AddonRegistry::class)->install(new ZipSource($zip)))
        ->toThrow(AddonInstallException::class);

    expect(File::isDirectory($this->root.'/acme-demo'))->toBeFalse();
});

// ── Update ──────────────────────────────────────────────────────────────────

it("update() replaces a bundled addon's link in place with the new version", function (): void {
    placeBundledAddon('Shipped', 'acme/shipped', '1.0.0');
    app(AddonDiscoveryService::class)->discoverNewAddons();

    $zip = addonZip($this->work, 'Shipped', 'acme/shipped', '1.1.0');
    app(AddonRegistry::class)->update('Shipped', new ZipSource($zip));

    // One entry in the root, now a real directory rather than the link.
    expect(is_link($this->root.'/shipped'))->toBeFalse()
        ->and(File::directories($this->root))->toHaveCount(1)
        ->and(realpath((string) Addon::query()->where('registry_id', 'acme/shipped')->value('path')))
        ->toBe(realpath($this->root.'/shipped'))
        ->and(Addon::query()->where('registry_id', 'acme/shipped')->value('version'))->toBe('1.1.0');
});

it('updating a bundled addon never touches the files in its bundled source', function (): void {
    // The hazard: File::deleteDirectory() on a symlink empties its target,
    // which for a bundled link is modules/<name>.
    $source = placeBundledAddon('Shipped', 'acme/shipped', '1.0.0');
    $manifest = File::get($source.'/addon.json');
    app(AddonDiscoveryService::class)->discoverNewAddons();

    app(AddonRegistry::class)->update('Shipped', new ZipSource(addonZip($this->work, 'Shipped', 'acme/shipped', '1.1.0')));

    expect(File::get($source.'/addon.json'))->toBe($manifest)
        ->and(File::get($source.'/resources/views/keep.blade.php'))->toBe('keep me');

    // And a second update, which now moves a real directory aside, still
    // leaves the bundled source alone.
    app(AddonRegistry::class)->update('Shipped', new ZipSource(addonZip($this->work, 'Shipped', 'acme/shipped', '1.2.0')));

    expect(File::get($source.'/addon.json'))->toBe($manifest)
        ->and(File::get($source.'/resources/views/keep.blade.php'))->toBe('keep me');
});

it('a failed update of a bundled addon restores the link and leaves its source intact', function (): void {
    $source = placeBundledAddon('Shipped', 'acme/shipped', '1.0.0');
    $manifest = File::get($source.'/addon.json');
    app(AddonDiscoveryService::class)->discoverNewAddons();
    $path = Addon::query()->where('registry_id', 'acme/shipped')->value('path');

    expect(fn () => app(AddonRegistry::class)->update(
        'Shipped',
        new ZipSource(addonZip($this->work, 'Shipped', 'acme/shipped', '1.1.0')),
        function (): void {
            throw new RuntimeException('migration blew up');
        },
    ))->toThrow(AddonInstallException::class);

    expect(is_link($this->root.'/shipped'))->toBeTrue()
        ->and(realpath($this->root.'/shipped'))->toBe(realpath($source))
        ->and(File::get($source.'/addon.json'))->toBe($manifest)
        ->and(File::get($source.'/resources/views/keep.blade.php'))->toBe('keep me')
        ->and(Addon::query()->where('registry_id', 'acme/shipped')->value('path'))->toBe($path)
        ->and(Addon::query()->where('registry_id', 'acme/shipped')->value('version'))->toBe('1.0.0')
        // No backup entry left behind in the root.
        ->and(array_values(array_diff(scandir($this->root), ['.', '..'])))->toBe(['shipped']);
});

// ── Delete ──────────────────────────────────────────────────────────────────

it('delete() disables a bundled addon and keeps its row and files', function (): void {
    $source = placeBundledAddon('Shipped', 'acme/shipped', '1.0.0');
    app(AddonDiscoveryService::class)->discoverNewAddons();
    Addon::query()->where('registry_id', 'acme/shipped')->update(['enabled' => true, 'bundled' => false]);

    expect(app(AddonRegistry::class)->delete('Shipped'))->toBe(AddonDeleteOutcome::Disabled);

    $addon = Addon::query()->where('registry_id', 'acme/shipped')->first();

    expect($addon)->not->toBeNull()
        ->and($addon->enabled)->toBeFalse()
        ->and(is_link($this->root.'/shipped'))->toBeTrue()
        ->and(File::exists($source.'/addon.json'))->toBeTrue();
});

it('delete() removes the files of an addon in the root', function (): void {
    $dir = placeAddonInRoot($this->root, 'Added', 'acme/added');
    app(AddonDiscoveryService::class)->discoverNewAddons();

    app(AddonRegistry::class)->delete('Added');

    expect(Addon::query()->where('registry_id', 'acme/added')->exists())->toBeFalse()
        ->and(File::isDirectory($dir))->toBeFalse();
});

it('a deleted addon does not come back on the next scan', function (): void {
    placeAddonInRoot($this->root, 'Added', 'acme/added');
    app(AddonDiscoveryService::class)->discoverNewAddons();

    app(AddonRegistry::class)->delete('Added');
    app(AddonDiscoveryService::class)->discoverNewAddons();

    // Without the file removal, discoverNewAddons() finds the untouched
    // manifest and re-adds a row — the addon silently reappears.
    expect(Addon::query()->where('registry_id', 'acme/added')->exists())->toBeFalse();
});

it('delete() leaves files alone when the stored path is outside the root', function (): void {
    // A hand-edited or legacy row must not be able to aim the recursive delete
    // somewhere else.
    $outside = $this->work.'/elsewhere';
    File::ensureDirectoryExists($outside);
    File::put($outside.'/keep.txt', 'keep me');

    Addon::factory()->create([
        'name'        => 'Stray',
        'registry_id' => 'acme/stray',
        'path'        => $outside,
    ]);

    app(AddonRegistry::class)->delete('Stray');

    expect(Addon::query()->where('registry_id', 'acme/stray')->exists())->toBeFalse()
        ->and(File::exists($outside.'/keep.txt'))->toBeTrue();
});

it('delete() disables a symlinked dev checkout and keeps its files', function (): void {
    // The standard local dev workflow: a link in the root to an external
    // checkout. Discovery resolves the link and stores the TARGET, which lies
    // outside the root, so delete() must not try to remove files it has no
    // business touching.
    $target = $this->work.'/external-checkout';
    File::ensureDirectoryExists($target);
    File::put($target.'/addon.json', json_encode(addonManifest([
        'name'        => 'Linked',
        'registry_id' => 'acme/linked',
        'version'     => '1.0.0',
        'providers'   => [],
    ])));
    File::put($target.'/composer.json', json_encode([
        'autoload' => ['psr-4' => ['Modules\\Linked\\' => 'app/']],
    ]));

    symlink($target, $this->root.'/phpvms-linked');

    app(AddonDiscoveryService::class)->discoverNewAddons();

    $stored = Addon::query()->where('registry_id', 'acme/linked')->value('path');
    expect($stored)->not->toStartWith($this->rootReal);

    $outcome = app(AddonRegistry::class)->delete('Linked');

    expect($outcome)->toBe(AddonDeleteOutcome::Disabled)
        ->and(Addon::query()->where('registry_id', 'acme/linked')->value('enabled'))->toBeFalsy()
        ->and(File::exists($target.'/addon.json'))->toBeTrue();
});

it('delete() of a registry update of a bundled addon reverts to the bundled link', function (): void {
    // Not an uninstall: the release still bundles the addon, so removing the
    // update hands it back to the bundled version. The row survives,
    // re-points at the bundled copy, and the addon keeps running.
    $source = placeBundledAddon('Twin', 'acme/twin', '1.0.0');
    app(AddonDiscoveryService::class)->discoverNewAddons();
    app(AddonRegistry::class)->update('Twin', new ZipSource(addonZip($this->work, 'Twin', 'acme/twin', '1.1.0')));

    expect(is_link($this->root.'/twin'))->toBeFalse();

    $outcome = app(AddonRegistry::class)->delete('Twin');

    expect($outcome)->toBe(AddonDeleteOutcome::Reverted)
        ->and(is_link($this->root.'/twin'))->toBeTrue()
        ->and(File::exists($source.'/addon.json'))->toBeTrue()
        ->and(Addon::query()->where('registry_id', 'acme/twin')->exists())->toBeTrue()
        ->and(Addon::query()->where('registry_id', 'acme/twin')->value('path'))->toBe(realpath($source))
        ->and(Addon::query()->where('registry_id', 'acme/twin')->value('version'))->toBe('1.0.0');
});
