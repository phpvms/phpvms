<?php

declare(strict_types=1);

use App\Addons\AddonRegistry;
use App\Models\Addon;
use App\Models\Kvp;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    // The addons migration seeds bundled module rows; clear them so each test
    // starts with a known-empty state.
    Addon::query()->delete();

    $this->addonPath = sys_get_temp_dir().'/phpvms-addon-contract-'.uniqid('', true);
    $migrationDir = $this->addonPath.'/database/migrations';
    File::ensureDirectoryExists($migrationDir);

    // Declared contract: the addon owns fixture_contract_things.
    File::put($this->addonPath.'/module.json', json_encode([
        'name'      => 'FixtureContract',
        'alias'     => 'fixturecontract',
        'providers' => [],
        'database'  => [
            'tables' => ['fixture_contract_things'],
        ],
    ]));

    // A valid addon requires composer.json alongside module.json.
    File::put($this->addonPath.'/composer.json', json_encode([
        'autoload' => ['psr-4' => ['Modules\\FixtureContract\\' => 'app/']],
    ]));

    // Migration with a deliberately no-op down(): proves uninstall drops the
    // table via the declared contract, not via the migration's down().
    File::put($migrationDir.'/2099_01_01_000000_create_fixture_contract_things_table.php', <<<'PHP'
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class() extends Migration
        {
            public function up(): void
            {
                Schema::create('fixture_contract_things', function (Blueprint $table): void {
                    $table->id();
                    $table->string('name');
                });
            }

            public function down(): void
            {
                // Intentionally empty — the contract drives table removal.
            }
        };
        PHP);

    Addon::factory()->create([
        'name'    => 'FixtureContract',
        'version' => '1.0.0',
        'path'    => $this->addonPath,
        'enabled' => false,
    ]);

    Artisan::call('migrate', [
        '--force'    => true,
        '--realpath' => true,
        '--path'     => [$migrationDir],
    ]);

    $this->registry = app(AddonRegistry::class);
});

afterEach(function (): void {
    if (is_string($this->addonPath ?? null) && str_starts_with($this->addonPath, sys_get_temp_dir())) {
        File::deleteDirectory($this->addonPath);
    }
});

it('drops declared tables on uninstall even when down() is a no-op', function (): void {
    expect(Schema::hasTable('fixture_contract_things'))->toBeTrue();

    Kvp::updateOrCreate(['key' => 'addon_seeded:FixtureContract:1.0.0'], ['value' => '1']);

    $this->registry->delete('FixtureContract', true);

    expect(Schema::hasTable('fixture_contract_things'))->toBeFalse()
        ->and(DB::table('migrations')->where('migration', 'like', '%create_fixture_contract_things_table')->exists())->toBeFalse()
        ->and(Kvp::where('key', 'addon_seeded:FixtureContract:1.0.0')->exists())->toBeFalse()
        ->and(Addon::query()->where('name', 'FixtureContract')->exists())->toBeFalse();
});

it('only drops declared tables, leaving core tables intact', function (): void {
    $this->registry->delete('FixtureContract', true);

    expect(Schema::hasTable('fixture_contract_things'))->toBeFalse()
        ->and(Schema::hasTable('settings'))->toBeTrue();
});

it('keeps declared tables when removeTables is false', function (): void {
    $this->registry->delete('FixtureContract', false);

    expect(Schema::hasTable('fixture_contract_things'))->toBeTrue()
        ->and(Addon::query()->where('name', 'FixtureContract')->exists())->toBeFalse();
});
