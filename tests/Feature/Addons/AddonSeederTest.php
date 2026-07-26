<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Kvp;
use App\Services\Installer\SeederService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    $this->seederSvc = app(SeederService::class);
});

afterEach(function (): void {
    if (is_string($this->addonPath ?? null) && str_starts_with($this->addonPath, sys_get_temp_dir())) {
        File::deleteDirectory($this->addonPath);
    }
});

function makeFixtureAddon(string $path): Addon
{
    return Addon::factory()->create([
        'name'      => 'FixtureAddon',
        'namespace' => 'Modules\\FixtureAddon',
        'version'   => '1.0.0',
        'path'      => $path,
        'enabled'   => true,
    ]);
}

it('reports an enabled addon with an unseeded seeder as pending', function (): void {
    makeFixtureAddon($this->addonPath);

    expect($this->seederSvc->addonSeedsPending())->toBeTrue();
});

it('runs addon seeders by file path and records a seed marker', function (): void {
    makeFixtureAddon($this->addonPath);

    $this->seederSvc->seedAddons();

    expect(Kvp::where('key', 'fixture_addon_proof')->exists())->toBeTrue()
        ->and(Kvp::where('key', 'addon_seeded:modules-fixtureaddon:1.0.0')->exists())->toBeTrue()
        ->and($this->seederSvc->addonSeedsPending())->toBeFalse();
});

it('does not flag a disabled addon as pending', function (): void {
    makeFixtureAddon($this->addonPath)->update(['enabled' => false]);

    expect($this->seederSvc->addonSeedsPending())->toBeFalse();
});

it('ignores enabled addons that ship no seeder directory', function (): void {
    Addon::factory()->create([
        'name'    => 'NoSeeds',
        'path'    => sys_get_temp_dir().'/phpvms-addon-noseeds-'.uniqid('', true),
        'enabled' => true,
    ]);

    expect($this->seederSvc->addonSeedsPending())->toBeFalse();
});

it('surfaces addon seed state through seedsPending()', function (): void {
    makeFixtureAddon($this->addonPath);

    expect($this->seederSvc->seedsPending())->toBeTrue();

    $this->seederSvc->seedAddons();

    expect($this->seederSvc->seedsPending())->toBeFalse();
});

it('logs the underlying exception message when an addon seeder throws, and still seeds the next addon', function (): void {
    // Use a dedicated addon path/class rather than the beforeEach fixture:
    // runSeederFile() checks class_exists($class, false) before requiring the
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
        'name'      => 'ThrowingAddon',
        'namespace' => 'Modules\\ThrowingAddon',
        'version'   => '1.0.0',
        'path'      => $throwingPath,
        'enabled'   => true,
    ]);

    // A second, well-behaved addon must still seed even though the throwing
    // addon above fails.
    makeFixtureAddon($this->addonPath);

    Log::spy();

    $this->seederSvc->seedAddons();

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => str_starts_with($message, 'Addon "ThrowingAddon" seeder failed; continuing: ')
            && str_contains($message, 'SQLSTATE[42P01]: Undefined table: relation "vmsacars_rules" does not exist')
            && !str_contains($message, "\n")
            && ($context['exception'] ?? null) instanceof Throwable);

    expect(Kvp::where('key', 'addon_seeded:modules-throwingaddon:1.0.0')->exists())->toBeFalse()
        ->and(Kvp::where('key', 'fixture_addon_proof')->exists())->toBeTrue()
        ->and(Kvp::where('key', 'addon_seeded:modules-fixtureaddon:1.0.0')->exists())->toBeTrue();

    File::deleteDirectory($throwingPath);
});
