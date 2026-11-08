<?php

declare(strict_types=1);

use App\Addons\Support\BootCache;
use App\Models\Addon;
use Illuminate\Support\Facades\File;

function renameDashboardAddonMigration(): object
{
    return require base_path('database/migrations_data/2026_11_05_000000_rename_phpvms_dashboard_addon.php');
}

function seedOldDashboardAddon(bool $enabled): void
{
    Addon::factory()->create([
        'name'        => 'PhpvmsDashboard',
        'registry_id' => 'phpvms/dashboard',
        'namespace'   => 'Modules\\PhpvmsDashboard',
        'path'        => base_path('modules/phpvms-dashboard'),
        'enabled'     => $enabled,
    ]);
}

beforeEach(function (): void {
    Addon::query()->whereIn('registry_id', ['phpvms/dashboard', 'phpvms/widgets'])->delete();

    $this->assets = sys_get_temp_dir().'/phpvms-ext-'.uniqid('', true);
    config(['addons.paths.assets' => $this->assets]);
});

afterEach(function (): void {
    File::deleteDirectory($this->assets);
});

it('replaces an enabled old addon with the new one, enabled and cached, and drops the old link', function (): void {
    seedOldDashboardAddon(enabled: true);
    File::ensureDirectoryExists($this->assets);
    symlink(sys_get_temp_dir(), $this->assets.'/phpvmsdashboard');

    renameDashboardAddonMigration()->up();

    $namespaces = app(BootCache::class)->enabled()->map(fn ($entry): string => $entry->namespace)->all();

    expect(Addon::query()->where('registry_id', 'phpvms/dashboard')->exists())->toBeFalse()
        ->and(Addon::query()->where('registry_id', 'phpvms/widgets')->value('enabled'))->toBeTruthy()
        ->and($namespaces)->toContain('Modules\\PhpvmsWidgets')
        ->and(is_link($this->assets.'/phpvmsdashboard'))->toBeFalse();
});

it('leaves the new addon disabled when the old one was disabled', function (): void {
    seedOldDashboardAddon(enabled: false);

    renameDashboardAddonMigration()->up();

    expect(Addon::query()->where('registry_id', 'phpvms/dashboard')->exists())->toBeFalse()
        ->and(Addon::query()->where('registry_id', 'phpvms/widgets')->sole()->enabled)->toBeFalse();
});

it('does nothing on an install that never had the old addon', function (): void {
    renameDashboardAddonMigration()->up();

    expect(Addon::query()->where('registry_id', 'phpvms/widgets')->exists())->toBeFalse();
});
