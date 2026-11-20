<?php

declare(strict_types=1);

use App\Features\Addons\Actions\PurgeAddonMigrationRecords;
use App\Features\Addons\AddonRegistry;
use App\Models\Addon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Addon::query()->delete();

    $this->addonPath = sys_get_temp_dir().'/phpvms-addon-setup-'.uniqid('', true);
    File::ensureDirectoryExists($this->addonPath.'/database/migrations');
    File::ensureDirectoryExists($this->addonPath.'/database/seeders');

    File::put($this->addonPath.'/database/migrations/2026_01_01_000000_create_ras_widgets.php', <<<'PHP'
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration {
            public function up(): void
            {
                Schema::create('ras_widgets', function (Blueprint $table): void {
                    $table->id();
                    $table->string('name');
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('ras_widgets');
            }
        };
        PHP);

    // Inserts into the migrated table, so it only succeeds after the migration.
    File::put($this->addonPath.'/database/seeders/RasDatabaseSeeder.php', <<<'PHP'
        <?php

        namespace PhpvmsTests\Fixtures\RunAddonSetup;

        use Illuminate\Database\Seeder;
        use Illuminate\Support\Facades\DB;

        class RasDatabaseSeeder extends Seeder
        {
            public function run(): void
            {
                DB::table('ras_widgets')->insert(['name' => 'seeded']);
            }
        }
        PHP);

    $this->addon = Addon::factory()->create([
        'name'        => 'SetupFixture',
        'registry_id' => 'acme/setup-fixture',
        'namespace'   => 'Modules\\SetupFixture',
        'version'     => '1.0.0',
        'path'        => $this->addonPath,
        'enabled'     => false,
    ]);
});

afterEach(function (): void {
    app(PurgeAddonMigrationRecords::class)->handle($this->addon);
    Schema::dropIfExists('ras_widgets');
    File::deleteDirectory($this->addonPath);
});

it('enabling an addon runs its migrations then its seeders once', function (): void {
    $registry = app(AddonRegistry::class);

    // The seeder writes into the migrated table, so a row proves the order.
    $registry->enable('SetupFixture');

    expect(DB::table('ras_widgets')->count())->toBe(1);

    $registry->disable('SetupFixture');
    $registry->enable('SetupFixture');

    expect(DB::table('ras_widgets')->count())->toBe(1);
});

it('leaves the addon disabled when its migration fails', function (): void {
    File::put($this->addonPath.'/database/migrations/2026_01_02_000000_boom.php', <<<'PHP'
        <?php

        use Illuminate\Database\Migrations\Migration;

        return new class extends Migration {
            public function up(): void
            {
                throw new RuntimeException('boom');
            }
        };
        PHP);

    expect(fn () => app(AddonRegistry::class)->enable('SetupFixture'))->toThrow(RuntimeException::class, 'boom')
        ->and($this->addon->refresh()->enabled)->toBeFalse();
});
