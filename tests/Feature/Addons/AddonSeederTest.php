<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Kvp;
use App\Services\Installer\SeederService;
use Illuminate\Support\Facades\File;

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
        'name'    => 'FixtureAddon',
        'version' => '1.0.0',
        'path'    => $path,
        'enabled' => true,
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
        ->and(Kvp::where('key', 'addon_seeded:FixtureAddon:1.0.0')->exists())->toBeTrue()
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
