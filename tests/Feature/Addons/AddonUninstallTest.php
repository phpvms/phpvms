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

    $this->addonPath = sys_get_temp_dir().'/phpvms-addon-uninstall-'.uniqid('', true);
    $migrationDir = $this->addonPath.'/Database/migrations';
    File::ensureDirectoryExists($migrationDir);

    File::put($migrationDir.'/2099_01_01_000000_create_fixture_addon_things_table.php', <<<'PHP'
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class() extends Migration
        {
            public function up(): void
            {
                Schema::create('fixture_addon_things', function (Blueprint $table): void {
                    $table->id();
                    $table->string('name');
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('fixture_addon_things');
            }
        };
        PHP);

    $this->migrationDir = $migrationDir;

    Addon::factory()->create([
        'name'    => 'FixtureUninstall',
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

it('drops the addon tables and migration records when removeTables is true', function (): void {
    expect(Schema::hasTable('fixture_addon_things'))->toBeTrue();

    Kvp::updateOrCreate(['key' => 'addon_seeded:FixtureUninstall:1.0.0'], ['value' => '1']);

    $this->registry->delete('FixtureUninstall', true);

    expect(Schema::hasTable('fixture_addon_things'))->toBeFalse()
        ->and(DB::table('migrations')->where('migration', 'like', '%create_fixture_addon_things_table')->exists())->toBeFalse()
        ->and(Kvp::where('key', 'addon_seeded:FixtureUninstall:1.0.0')->exists())->toBeFalse()
        ->and(Addon::query()->where('name', 'FixtureUninstall')->exists())->toBeFalse();
});

it('keeps the addon tables when removeTables is false', function (): void {
    $this->registry->delete('FixtureUninstall', false);

    expect(Schema::hasTable('fixture_addon_things'))->toBeTrue()
        ->and(Addon::query()->where('name', 'FixtureUninstall')->exists())->toBeFalse();
});

it('defaults to keeping tables when no flag is passed', function (): void {
    $this->registry->delete('FixtureUninstall');

    expect(Schema::hasTable('fixture_addon_things'))->toBeTrue();
});

it('only rolls back the addon migrations, leaving core tables intact', function (): void {
    $this->registry->delete('FixtureUninstall', true);

    expect(Schema::hasTable('fixture_addon_things'))->toBeFalse()
        ->and(Schema::hasTable('settings'))->toBeTrue();
});
