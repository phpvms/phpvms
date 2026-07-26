<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Kvp;
use App\Services\Installer\SeederService;
use Illuminate\Support\Facades\File;

function rekeyAddonSeedMarkersMigration(): object
{
    return require base_path('database/migrations_data/2026_07_25_000001_rekey_addon_seed_markers.php');
}

/**
 * Create a fixture addon with a seeder directory, so seedAddons() has
 * something to run and mark. Mirrors AddonSeederTest's fixture.
 */
function makeSeedableAddon(array $attributes): Addon
{
    $path = sys_get_temp_dir().'/phpvms-addon-seedmarker-'.uniqid('', true);
    $seedDir = $path.'/database/seeders';
    File::ensureDirectoryExists($seedDir);

    File::put($seedDir.'/FixtureDatabaseSeeder.php', <<<'PHP'
        <?php

        namespace PhpvmsTests\Fixtures\AddonSeedMarker;

        use Illuminate\Database\Seeder;

        class FixtureDatabaseSeeder extends Seeder
        {
            public function run(): void
            {
            }
        }
        PHP);

    return Addon::factory()->create([...$attributes, 'path' => $path, 'enabled' => true]);
}

beforeEach(function (): void {
    // The addons migration seeds bundled module rows; clear them so each test
    // starts with a known-empty state and only our fixture addons are present.
    Addon::query()->delete();
});

afterEach(function (): void {
    foreach (Addon::query()->pluck('path') as $path) {
        if (is_string($path) && str_starts_with($path, sys_get_temp_dir())) {
            File::deleteDirectory($path);
        }
    }
});

it('keys the seed marker on registry_id when present', function (): void {
    makeSeedableAddon([
        'registry_id' => 'phpvms/acars',
        'namespace'   => 'Modules\\Acars',
        'version'     => '1.1.0',
    ]);

    app(SeederService::class)->seedAddons();

    expect(Kvp::where('key', 'addon_seeded:phpvms-acars:1.1.0')->exists())->toBeTrue();
});

it('falls back to namespace when registry_id is null', function (): void {
    makeSeedableAddon([
        'registry_id' => null,
        'namespace'   => 'Modules\\Sample',
        'version'     => '2.0.0',
    ]);

    app(SeederService::class)->seedAddons();

    expect(Kvp::where('key', 'addon_seeded:modules-sample:2.0.0')->exists())->toBeTrue();
});

it('does not collide two null-registry_id addons on different namespaces', function (): void {
    makeSeedableAddon([
        'registry_id' => null,
        'namespace'   => 'Modules\\Awards',
        'version'     => null,
    ]);

    makeSeedableAddon([
        'registry_id' => null,
        'namespace'   => 'Modules\\Sample',
        'version'     => null,
    ]);

    $seederSvc = app(SeederService::class);
    $seederSvc->seedAddons();

    expect(Kvp::where('key', 'addon_seeded:modules-awards:base')->exists())->toBeTrue()
        ->and(Kvp::where('key', 'addon_seeded:modules-sample:base')->exists())->toBeTrue()
        ->and($seederSvc->addonSeedsPending())->toBeFalse();
});

it('clears every version of an addon marker on uninstall', function (): void {
    $addon = Addon::factory()->create([
        'registry_id' => 'phpvms/acars',
        'namespace'   => 'Modules\\Acars',
        'version'     => '1.1.0',
    ]);

    Kvp::create(['key' => 'addon_seeded:phpvms-acars:1.0.0', 'value' => 'x']);
    Kvp::create(['key' => 'addon_seeded:phpvms-acars:1.1.0', 'value' => 'x']);
    Kvp::create(['key' => 'addon_seeded:phpvms-other:1.0.0', 'value' => 'x']);

    app(SeederService::class)->clearAddonSeedMarkers($addon);

    expect(Kvp::where('key', 'like', 'addon_seeded:phpvms-acars:%')->exists())->toBeFalse()
        ->and(Kvp::where('key', 'addon_seeded:phpvms-other:1.0.0')->exists())->toBeTrue();
});

it('slugifies the identity so the key carries no LIKE metacharacters', function (): void {
    // keyed_str() strips the backslash of a namespace and the slash of a
    // registry_id, along with any % or _ wildcard. That keeps the key safe to
    // interpolate into the uninstall LIKE pattern: a raw `Modules\Sample` would
    // behave differently on MySQL and Postgres (backslash is their default LIKE
    // escape character) than on sqlite (which has none).
    $addon = Addon::factory()->create([
        'registry_id' => null,
        'namespace'   => 'Modules\\Sample',
        'version'     => '1.0.0',
    ]);

    Kvp::create(['key' => 'addon_seeded:modules-sample:1.0.0', 'value' => 'x']);

    // Distinct addon whose namespace has no separator — keyed_str keeps the `-`
    // delimiter, so it must not collapse onto the same identity.
    Kvp::create(['key' => 'addon_seeded:modulessample:1.0.0', 'value' => 'x']);

    app(SeederService::class)->clearAddonSeedMarkers($addon);

    expect(Kvp::where('key', 'addon_seeded:modules-sample:1.0.0')->exists())->toBeFalse()
        ->and(Kvp::where('key', 'addon_seeded:modulessample:1.0.0')->exists())->toBeTrue();
});

it('rewrites a name-keyed marker to the new identity form', function (): void {
    Addon::factory()->create([
        'name'        => 'VMSAcars',
        'registry_id' => 'phpvms/acars',
        'namespace'   => 'Modules\\Acars',
    ]);

    Kvp::create(['key' => 'addon_seeded:VMSAcars:1.1.0', 'value' => '2026-01-01 00:00:00']);

    rekeyAddonSeedMarkersMigration()->up();

    expect(Kvp::where('key', 'addon_seeded:VMSAcars:1.1.0')->exists())->toBeFalse()
        ->and(Kvp::where('key', 'addon_seeded:phpvms-acars:1.1.0')->exists())->toBeTrue();
});

it('deletes a marker whose name resolves to no addon', function (): void {
    Kvp::create(['key' => 'addon_seeded:GhostAddon:1.0.0', 'value' => '2026-01-01 00:00:00']);

    rekeyAddonSeedMarkersMigration()->up();

    expect(Kvp::where('key', 'like', 'addon_seeded:GhostAddon:%')->exists())->toBeFalse();
});

it('is a no-op the second time it runs', function (): void {
    Addon::factory()->create([
        'name'        => 'VMSAcars',
        'registry_id' => 'phpvms/acars',
        'namespace'   => 'Modules\\Acars',
    ]);

    Kvp::create(['key' => 'addon_seeded:VMSAcars:1.1.0', 'value' => '2026-01-01 00:00:00']);

    $migration = rekeyAddonSeedMarkersMigration();
    $migration->up();
    $migration->up();

    expect(Kvp::where('key', 'addon_seeded:phpvms-acars:1.1.0')->count())->toBe(1)
        ->and(Kvp::where('key', 'like', 'addon_seeded:VMSAcars:%')->exists())->toBeFalse();
});
