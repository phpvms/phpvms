<?php

declare(strict_types=1);

use App\Addons\Support\BootCache;
use App\Models\Addon;
use Illuminate\Support\Facades\File;

function dropDashboardAssetsMigration(): object
{
    return require base_path('database/migrations_data/2026_11_09_000000_drop_phpvms_dashboard_assets.php');
}

beforeEach(function (): void {
    Addon::query()->whereIn('registry_id', ['phpvms/dashboard', 'phpvms/widgets'])->delete();

    $this->assets = sys_get_temp_dir().'/phpvms-ext-'.uniqid('', true);
    config(['addons.paths.assets' => $this->assets]);
});

afterEach(function (): void {
    File::deleteDirectory($this->assets);
});

it('drops the old asset link and caches the renamed addon', function (): void {
    Addon::factory()->create([
        'name'        => 'PhpvmsWidgets',
        'registry_id' => 'phpvms/widgets',
        'namespace'   => 'Modules\\PhpvmsWidgets',
        'path'        => base_path('modules/phpvms-widgets'),
        'enabled'     => true,
    ]);
    File::ensureDirectoryExists($this->assets);
    symlink(sys_get_temp_dir(), $this->assets.'/phpvmsdashboard');

    dropDashboardAssetsMigration()->up();

    $namespaces = app(BootCache::class)->enabled()->map(fn ($entry): string => $entry->namespace)->all();

    expect(is_link($this->assets.'/phpvmsdashboard'))->toBeFalse()
        ->and($namespaces)->toContain('Modules\\PhpvmsWidgets');
});
