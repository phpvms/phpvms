<?php

declare(strict_types=1);

use App\Features\Addons\Actions\RunAddonSetup;
use App\Features\Addons\AddonRegistry;
use App\Features\Addons\Support\AddonSeedMarkers;
use App\Models\Addon;
use App\Models\Kvp;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    // The addons migration seeds bundled module rows; clear them so each test
    // starts with a known-empty state and only our fixture addon is enabled.
    Addon::query()->delete();

    $this->addonPath = sys_get_temp_dir().'/phpvms-addon-seed-'.uniqid('', true);
    $seedDir = $this->addonPath.'/database/seeders';
    File::ensureDirectoryExists($seedDir);

    // Fixture seeder uses a deliberately non-PSR-4 namespace to prove the
    // service loads addon seeders by file path, not by autoloadable class name.
    File::put($seedDir.'/FixtureAddonDatabaseSeeder.php', <<<'PHP'
        <?php

        namespace PhpvmsTests\Fixtures\AddonSeed;

        use App\Models\Kvp;
        use Illuminate\Database\Seeder;

        class FixtureAddonDatabaseSeeder extends Seeder
        {
            public function run(): void
            {
                Kvp::updateOrCreate(['key' => 'fixture_addon_proof'], ['value' => '1']);
            }
        }
        PHP);

    $this->markers = app(AddonSeedMarkers::class);
});

afterEach(function (): void {
    if (is_string($this->addonPath ?? null) && str_starts_with($this->addonPath, sys_get_temp_dir())) {
        File::deleteDirectory($this->addonPath);
    }
});

function makeFixtureAddon(string $path): Addon
{
    return Addon::factory()->create([
        'name'        => 'FixtureAddon',
        'registry_id' => 'acme/fixture-addon',
        'namespace'   => 'Modules\\FixtureAddon',
        'version'     => '1.0.0',
        'path'        => $path,
        'enabled'     => true,
    ]);
}

it('reports an enabled addon with an unseeded seeder as pending', function (): void {
    makeFixtureAddon($this->addonPath);

    expect($this->markers->pending())->toBeTrue();
});

it('runs addon seeders by file path and records a seed marker', function (): void {
    makeFixtureAddon($this->addonPath);

    foreach (app(AddonRegistry::class)->enabled() as $addon) {
        app(RunAddonSetup::class)->handle($addon);
    }

    expect(Kvp::where('key', 'fixture_addon_proof')->exists())->toBeTrue()
        ->and(Kvp::where('key', 'addon_seeded:acme-fixture-addon:1.0.0')->exists())->toBeTrue()
        ->and($this->markers->pending())->toBeFalse();
});

it('does not flag a disabled addon as pending', function (): void {
    makeFixtureAddon($this->addonPath)->update(['enabled' => false]);

    expect($this->markers->pending())->toBeFalse();
});

it('ignores enabled addons that ship no seeder directory', function (): void {
    Addon::factory()->create([
        'name'    => 'NoSeeds',
        'path'    => sys_get_temp_dir().'/phpvms-addon-noseeds-'.uniqid('', true),
        'enabled' => true,
    ]);

    expect($this->markers->pending())->toBeFalse();
});

it('logs the underlying exception message when an addon seeder throws, and still seeds the next addon', function (): void {
    // Use a dedicated addon path/class rather than the beforeEach fixture:
    // RunAddonSetup checks class_exists($class, false) before requiring the
    // file, so re-declaring FixtureAddonDatabaseSeeder here would be shadowed
    // by the non-throwing class definition an earlier test already loaded into
    // this PHP process.
    $throwingPath = sys_get_temp_dir().'/phpvms-addon-seed-throwing-'.uniqid('', true);
    $throwingSeedDir = $throwingPath.'/database/seeders';
    File::ensureDirectoryExists($throwingSeedDir);
    File::put($throwingSeedDir.'/ThrowingAddonDatabaseSeeder.php', <<<'PHP'
        <?php

        namespace PhpvmsTests\Fixtures\AddonSeed;

        use Illuminate\Database\Seeder;
        use RuntimeException;

        class ThrowingAddonDatabaseSeeder extends Seeder
        {
            public function run(): void
            {
                throw new RuntimeException("SQLSTATE[42P01]: Undefined table:\n  relation \"vmsacars_rules\" does not exist");
            }
        }
        PHP);

    Addon::factory()->create([
        'name'        => 'ThrowingAddon',
        'registry_id' => 'acme/throwing-addon',
        'namespace'   => 'Modules\\ThrowingAddon',
        'version'     => '1.0.0',
        'path'        => $throwingPath,
        'enabled'     => true,
    ]);

    // A second, well-behaved addon must still seed even though the throwing
    // addon above fails.
    makeFixtureAddon($this->addonPath);

    Log::spy();

    foreach (app(AddonRegistry::class)->enabled() as $addon) {
        app(RunAddonSetup::class)->handle($addon);
    }

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => str_starts_with($message, 'Addon "ThrowingAddon" seeder failed; continuing: ')
            && str_contains($message, 'SQLSTATE[42P01]: Undefined table: relation "vmsacars_rules" does not exist')
            && !str_contains($message, "\n")
            && ($context['exception'] ?? null) instanceof Throwable);

    expect(Kvp::where('key', 'addon_seeded:acme-throwing-addon:1.0.0')->exists())->toBeFalse()
        ->and(Kvp::where('key', 'fixture_addon_proof')->exists())->toBeTrue()
        ->and(Kvp::where('key', 'addon_seeded:acme-fixture-addon:1.0.0')->exists())->toBeTrue();

    File::deleteDirectory($throwingPath);
});
