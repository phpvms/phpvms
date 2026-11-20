<?php

declare(strict_types=1);

use App\Features\Setup\Steps\SeedCore;
use App\Features\Setup\Support\StepContext;
use App\Models\Addon;
use App\Models\Fare;
use App\Models\Kvp;
use App\Models\Rank;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

function seedContext(bool $fresh, array &$lines): StepContext
{
    return new StepContext(
        $fresh,
        static function (string $line) use (&$lines): void {
            $lines[] = $line;
        },
        static function (int $done, int $total, ?string $item): void {},
    );
}

test('update path runs permission sync and keeps existing ranks', function (): void {
    Rank::query()->delete();
    Kvp::query()->where('key', 'ranks_seeded')->delete();
    Rank::factory()->create(['name' => 'Captain']);
    Rank::factory()->create(['name' => 'First Officer']);
    Permission::query()->delete();

    $lines = [];
    app(SeedCore::class)->run(seedContext(false, $lines));

    expect(Rank::query()->pluck('name')->sort()->values()->all())->toBe(['Captain', 'First Officer'])
        ->and(Permission::query()->count())->toBeGreaterThan(0)
        ->and($lines)->not->toBeEmpty();
});

test('the update path does not seed base data', function (): void {
    Fare::withTrashed()->forceDelete();
    Kvp::query()->where('key', 'fares_seeded')->delete();

    $lines = [];
    app(SeedCore::class)->run(seedContext(false, $lines));

    expect(Fare::withTrashed()->count())->toBe(0)
        ->and(Kvp::query()->where('key', 'fares_seeded')->exists())->toBeFalse();
});

test('fresh path runs DatabaseSeeder and no addon seeder', function (): void {
    $path = sys_get_temp_dir().'/phpvms-seedcore-'.uniqid('', true);
    File::ensureDirectoryExists($path.'/database/seeders');
    File::put($path.'/database/seeders/BoomSeeder.php', <<<'PHP'
        <?php

        namespace PhpvmsTests\Fixtures\SeedCore;

        use App\Models\Kvp;
        use Illuminate\Database\Seeder;

        class BoomSeeder extends Seeder
        {
            public function run(): void
            {
                Kvp::updateOrCreate(['key' => 'boom_seeder_ran'], ['value' => '1']);

                throw new \RuntimeException('addon seeder must not run');
            }
        }
        PHP);

    Addon::factory()->create([
        'name'        => 'BoomAddon',
        'registry_id' => 'acme/boom-addon',
        'namespace'   => 'Modules\\BoomAddon',
        'version'     => '1.0.0',
        'path'        => $path,
        'enabled'     => true,
    ]);

    $lines = [];

    try {
        app(SeedCore::class)->run(seedContext(true, $lines));
    } finally {
        File::deleteDirectory($path);
    }

    expect(Kvp::query()->where('key', 'ranks_seeded')->exists())->toBeTrue()
        ->and(Kvp::query()->where('key', 'boom_seeder_ran')->exists())->toBeFalse();
});
