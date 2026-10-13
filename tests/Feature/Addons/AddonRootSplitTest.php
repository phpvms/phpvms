<?php

declare(strict_types=1);

use App\Addons\AddonDeleteOutcome;
use App\Addons\AddonRegistry;
use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Sources\ZipSource;
use App\Exceptions\AddonInstallException;
use App\Models\Addon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

/**
 * The two addon roots: `addons.paths.base` ships inside the deployment image
 * and is read-only at runtime; `addons.paths.installed` is the writable volume
 * the addon manager installs into.
 *
 * Both are pointed at temp directories here. tests/Pest.php already redirects
 * the installed root away from the working copy for every test; this suite
 * additionally takes over the image root so it can place files in both.
 */
beforeEach(function (): void {
    Addon::query()->delete();

    $this->work = sys_get_temp_dir().'/addon-roots-'.uniqid('', true);
    $this->image = $this->work.'/image';
    $this->installed = $this->work.'/installed';

    File::ensureDirectoryExists($this->image);
    File::ensureDirectoryExists($this->installed);

    // Discovery stores realpath()-resolved paths, and on macOS the system temp
    // dir is itself a symlink (/var/folders -> /private/var/folders), so the
    // resolved form is what path assertions below have to compare against.
    $this->imageReal = realpath($this->image);
    $this->installedReal = realpath($this->installed);

    Config::set('addons.paths.base', $this->image);
    Config::set('addons.paths.installed', $this->installed);
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

    File::put($dir.'/module.json', json_encode($manifest));
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
    $zip->addFromString('module.json', json_encode(array_filter([
        'name'        => $name,
        'registry_id' => $registryId,
        'version'     => $version,
        'providers'   => [],
    ], static fn (string|array|null $value): bool => $value !== null)));
    $zip->addFromString('composer.json', json_encode([
        'autoload' => ['psr-4' => ['Modules\\'.$name.'\\' => '']],
    ]));
    $zip->close();

    return $path;
}

// ── Discovery spans both roots ──────────────────────────────────────────────

it('discovers addons from both the image root and the writable root', function (): void {
    placeAddonInRoot($this->image, 'Shipped');
    placeAddonInRoot($this->installed, 'Added');

    app(AddonDiscoveryService::class)->discoverNewAddons();

    expect(Addon::query()->pluck('name')->sort()->values()->all())
        ->toBe(['Added', 'Shipped']);
});

it('records each addon against the root it was found in', function (): void {
    placeAddonInRoot($this->image, 'Shipped');
    placeAddonInRoot($this->installed, 'Added');

    app(AddonDiscoveryService::class)->discoverNewAddons();

    expect(Addon::query()->where('name', 'Shipped')->value('path'))->toStartWith($this->imageReal)
        ->and(Addon::query()->where('name', 'Added')->value('path'))->toStartWith($this->installedReal);
});

it('loads the newer registry copy over the bundled one', function (): void {
    // The case this whole rule exists for: phpVMS ships a module, the operator
    // updates it from the registry, and the update has to actually take effect.
    placeAddonInRoot($this->image, 'Twin', 'acme/twin', '1.0.0');
    placeAddonInRoot($this->installed, 'Twin', 'acme/twin', '1.1.0');

    app(AddonDiscoveryService::class)->discoverNewAddons();

    expect(Addon::query()->where('registry_id', 'acme/twin')->count())->toBe(1)
        ->and(Addon::query()->where('registry_id', 'acme/twin')->value('path'))
        ->toStartWith($this->installedReal);
});

it('loads the bundled copy again once a deploy ships a newer version', function (): void {
    // The other direction, and the reason fixed installed-wins is unsafe: a
    // later image must be able to supersede an older runtime install.
    placeAddonInRoot($this->image, 'Twin', 'acme/twin', '1.2.0');
    placeAddonInRoot($this->installed, 'Twin', 'acme/twin', '1.1.0');

    app(AddonDiscoveryService::class)->discoverNewAddons();

    expect(Addon::query()->where('registry_id', 'acme/twin')->value('path'))
        ->toStartWith($this->imageReal);
});

it('prefers the bundled copy on equal versions', function (): void {
    placeAddonInRoot($this->image, 'Twin', 'acme/twin', '1.1.0');
    placeAddonInRoot($this->installed, 'Twin', 'acme/twin', '1.1.0');

    app(AddonDiscoveryService::class)->discoverNewAddons();

    expect(Addon::query()->where('registry_id', 'acme/twin')->value('path'))
        ->toStartWith($this->imageReal);
});

it('prefers the bundled copy when the installed one declares no version', function (): void {
    // Fails toward the image deliberately. An unversioned runtime copy that
    // could win would shadow the image permanently, with no deploy able to
    // take it back — the staleness bug the two roots exist to prevent.
    placeAddonInRoot($this->image, 'Twin', 'acme/twin', '1.0.0');
    placeAddonInRoot($this->installed, 'Twin', 'acme/twin');

    app(AddonDiscoveryService::class)->discoverNewAddons();

    expect(Addon::query()->where('registry_id', 'acme/twin')->value('path'))
        ->toStartWith($this->imageReal);
});

it('prefers the bundled copy when the bundled one declares no version', function (): void {
    // Same direction: a bundled module that forgets to declare a version is
    // simply not registry-updatable, rather than permanently overridable.
    placeAddonInRoot($this->image, 'Twin', 'acme/twin');
    placeAddonInRoot($this->installed, 'Twin', 'acme/twin', '9.9.9');

    app(AddonDiscoveryService::class)->discoverNewAddons();

    expect(Addon::query()->where('registry_id', 'acme/twin')->value('path'))
        ->toStartWith($this->imageReal);
});

it('treats the pre-split layout, both keys on one directory, as a single root', function (): void {
    Config::set('addons.paths.installed', $this->image);
    placeAddonInRoot($this->image, 'Solo');

    app(AddonDiscoveryService::class)->discoverNewAddons();

    expect(Addon::query()->where('name', 'Solo')->count())->toBe(1);
});

it('invalidates the boot cache when an addon appears in the writable root', function (): void {
    placeAddonInRoot($this->image, 'Shipped');
    $service = app(AddonDiscoveryService::class);
    $service->discoverNewAddons();
    $service->rebuildCache();

    expect($service->primeIfNeeded())->toBeFalse();

    // A new addon in the writable root must make the cache stale. Before the
    // root split, diskFingerprint() only hashed the image root and missed this.
    placeAddonInRoot($this->installed, 'Added');

    expect($service->primeIfNeeded())->toBeTrue();
});

// ── Runtime writes only ever touch the writable root ───────────────────────

it('install() rejects an addon already shipped in the image root', function (): void {
    // Placed in the image root under a directory name that does NOT match the
    // safeName install() would pick ('acme-demo') — exactly how the real
    // bundled modules are laid out (modules/Awards, modules/phpvms-acars). A
    // directory-name comparison would miss this; registry_id catches it.
    placeAddonInRoot($this->image, 'Demo', 'acme/demo');
    app(AddonDiscoveryService::class)->discoverNewAddons();

    $zip = addonZip($this->work, 'Demo', 'acme/demo');

    expect(fn () => app(AddonRegistry::class)->install(new ZipSource($zip)))
        ->toThrow(AddonInstallException::class);

    // And nothing was left behind in the writable root.
    expect(File::isDirectory($this->installed.'/acme-demo'))->toBeFalse();
});

it("update() places a bundled addon's new version in the writable root", function (): void {
    $dir = placeAddonInRoot($this->image, 'Shipped', 'acme/shipped', '1.0.0');
    app(AddonDiscoveryService::class)->discoverNewAddons();

    $zip = addonZip($this->work, 'Shipped', 'acme/shipped', '1.1.0');
    app(AddonRegistry::class)->update('Shipped', new ZipSource($zip));

    // Image copy untouched, new version in the writable root, and the row now
    // points at the copy that actually loads.
    expect(File::isDirectory($dir))->toBeTrue()
        ->and(File::isDirectory($this->installed.'/acme-shipped'))->toBeTrue()
        ->and(realpath((string) Addon::query()->where('registry_id', 'acme/shipped')->value('path')))
        ->toStartWith($this->installedReal);
});

it('a failed update of a bundled addon leaves no copy in the writable root', function (): void {
    // $backup is null here — nothing was at $dest to move aside — so the
    // restore path has to delete the half-placed copy itself. Left behind, a
    // broken newer-looking copy would win precedence over the working image one.
    placeAddonInRoot($this->image, 'Shipped', 'acme/shipped', '1.0.0');
    app(AddonDiscoveryService::class)->discoverNewAddons();

    $zip = addonZip($this->work, 'Shipped', 'acme/shipped', '1.1.0');

    expect(fn () => app(AddonRegistry::class)->update(
        'Shipped',
        new ZipSource($zip),
        function (): void {
            throw new RuntimeException('migration blew up');
        },
    ))->toThrow(AddonInstallException::class);

    expect(File::isDirectory($this->installed.'/acme-shipped'))->toBeFalse()
        ->and(Addon::query()->where('registry_id', 'acme/shipped')->value('path'))
        ->toStartWith($this->imageReal);
});

// ── Delete means different things per root ─────────────────────────────────

it('delete() disables an image-shipped addon and keeps its row and files', function (): void {
    $dir = placeAddonInRoot($this->image, 'Shipped', 'acme/shipped');

    // Real discovery again, for the resolved-path reason above.
    app(AddonDiscoveryService::class)->discoverNewAddons();
    Addon::query()->where('registry_id', 'acme/shipped')->update(['enabled' => true]);

    app(AddonRegistry::class)->delete('Shipped');

    // Row kept, so it stays listed in the admin UI and stays switchable, and
    // so discoverNewAddons() does not re-add it as a brand new addon.
    $addon = Addon::query()->where('registry_id', 'acme/shipped')->first();

    expect($addon)->not->toBeNull()
        ->and($addon->enabled)->toBeFalse()
        ->and(File::isDirectory($dir))->toBeTrue();
});

it('delete() removes the files of an addon in the writable root', function (): void {
    $dir = placeAddonInRoot($this->installed, 'Added', 'acme/added');
    app(AddonDiscoveryService::class)->discoverNewAddons();

    app(AddonRegistry::class)->delete('Added');

    expect(Addon::query()->where('registry_id', 'acme/added')->exists())->toBeFalse()
        ->and(File::isDirectory($dir))->toBeFalse();
});

it('a deleted addon in the writable root does not come back on the next scan', function (): void {
    placeAddonInRoot($this->installed, 'Added', 'acme/added');
    app(AddonDiscoveryService::class)->discoverNewAddons();

    app(AddonRegistry::class)->delete('Added');
    app(AddonDiscoveryService::class)->discoverNewAddons();

    // Without the file removal, discoverNewAddons() finds the untouched
    // manifest and re-adds a row — the addon silently reappears.
    expect(Addon::query()->where('registry_id', 'acme/added')->exists())->toBeFalse();
});

it('delete() leaves files alone when the stored path is outside the writable root', function (): void {
    // A hand-edited or legacy row must not be able to aim the recursive delete
    // somewhere else. Outside both roots, so the image-managed branch does not
    // claim it first.
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

it('treats a symlinked module in the image root as image-managed', function (): void {
    // The standard local dev workflow: modules/phpvms-acars -> an external
    // checkout. Discovery resolves the symlink and stores the TARGET, which
    // lies outside the image root, so a prefix test on the stored path alone
    // would call this runtime-managed — and delete() would then try to remove
    // files it has no business touching.
    $target = $this->work.'/external-checkout';
    File::ensureDirectoryExists($target);
    File::put($target.'/module.json', json_encode([
        'name'        => 'Linked',
        'registry_id' => 'acme/linked',
        'version'     => '1.0.0',
        'providers'   => [],
    ]));
    File::put($target.'/composer.json', json_encode([
        'autoload' => ['psr-4' => ['Modules\\Linked\\' => 'app/']],
    ]));

    symlink($target, $this->image.'/phpvms-linked');

    app(AddonDiscoveryService::class)->discoverNewAddons();

    $stored = Addon::query()->where('registry_id', 'acme/linked')->value('path');
    expect($stored)->not->toStartWith($this->imageReal);

    // Still image-managed: delete() disables and keeps both row and files
    // rather than deleting someone's external checkout.
    $outcome = app(AddonRegistry::class)->delete('Linked');

    expect($outcome)->toBe(AddonDeleteOutcome::Disabled)
        ->and(Addon::query()->where('registry_id', 'acme/linked')->value('enabled'))->toBeFalsy()
        ->and(File::exists($target.'/module.json'))->toBeTrue();
});

it('delete() of a registry update reverts to the bundled version', function (): void {
    // Not an uninstall: the image still ships the addon, so removing the
    // runtime copy hands it back to the bundled version. The row survives,
    // re-points at the image copy, and the addon keeps running.
    $imageDir = placeAddonInRoot($this->image, 'Twin', 'acme/twin', '1.0.0');
    $installedDir = placeAddonInRoot($this->installed, 'Twin', 'acme/twin', '1.1.0');
    app(AddonDiscoveryService::class)->discoverNewAddons();

    expect(Addon::query()->where('registry_id', 'acme/twin')->value('path'))
        ->toStartWith($this->installedReal);

    $outcome = app(AddonRegistry::class)->delete('Twin');

    expect($outcome)->toBe(AddonDeleteOutcome::Reverted)
        ->and(File::isDirectory($installedDir))->toBeFalse()
        ->and(File::isDirectory($imageDir))->toBeTrue()
        ->and(Addon::query()->where('registry_id', 'acme/twin')->exists())->toBeTrue()
        ->and(Addon::query()->where('registry_id', 'acme/twin')->value('path'))
        ->toStartWith($this->imageReal)
        ->and(Addon::query()->where('registry_id', 'acme/twin')->value('version'))->toBe('1.0.0');
});

it('re-points an existing row when a deploy ships a newer bundled version', function (): void {
    // The sequence a real deployment actually follows, and the one a fresh-row
    // test cannot catch: the image copy is discovered first and seeds the row,
    // the operator installs a newer copy from the registry, then a deploy ships
    // a newer bundled version again. Each step has to move the row.
    $imageDir = placeAddonInRoot($this->image, 'Twin', 'acme/twin', '1.0.0');
    app(AddonDiscoveryService::class)->discoverNewAddons();

    expect(Addon::query()->where('registry_id', 'acme/twin')->value('path'))
        ->toStartWith($this->imageReal);

    $installedDir = placeAddonInRoot($this->installed, 'Twin', 'acme/twin', '1.1.0');
    app(AddonDiscoveryService::class)->discoverNewAddons();

    expect(Addon::query()->where('registry_id', 'acme/twin')->value('path'))
        ->toStartWith($this->installedReal)
        ->and(Addon::query()->where('registry_id', 'acme/twin')->value('version'))->toBe('1.1.0');

    // Deploy ships 1.2.0: the bundled copy wins again and the row follows it.
    File::put($imageDir.'/module.json', json_encode([
        'name'        => 'Twin',
        'registry_id' => 'acme/twin',
        'version'     => '1.2.0',
        'providers'   => [],
    ]));
    app(AddonDiscoveryService::class)->discoverNewAddons();

    expect(Addon::query()->where('registry_id', 'acme/twin')->value('path'))
        ->toStartWith($this->imageReal)
        ->and(Addon::query()->where('registry_id', 'acme/twin')->value('version'))->toBe('1.2.0')
        ->and(File::isDirectory($installedDir))->toBeTrue();
});

it('does not re-enable an addon the operator disabled', function (): void {
    // Reconciling an existing row must not touch `enabled` (D-12).
    placeAddonInRoot($this->image, 'Twin', 'acme/twin', '1.0.0');
    app(AddonDiscoveryService::class)->discoverNewAddons();
    Addon::query()->where('registry_id', 'acme/twin')->update(['enabled' => false]);

    placeAddonInRoot($this->installed, 'Twin', 'acme/twin', '1.1.0');
    app(AddonDiscoveryService::class)->discoverNewAddons();

    expect(Addon::query()->where('registry_id', 'acme/twin')->value('enabled'))->toBeFalsy()
        ->and(Addon::query()->where('registry_id', 'acme/twin')->value('path'))
        ->toStartWith($this->installedReal);
});
