<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

function renameDashboardAddonMigration(): object
{
    return require base_path('database/migrations_data/2026_11_05_000000_rename_phpvms_dashboard_addon.php');
}

/**
 * @param array<string, mixed> $overrides
 */
function seedAddonRow(array $overrides): int
{
    return DB::table('addons')->insertGetId([
        'name'         => 'PhpvmsDashboard',
        'registry_id'  => 'phpvms/dashboard',
        'type'         => 'module',
        'namespace'    => 'Modules\\PhpvmsDashboard',
        'path'         => base_path('modules/phpvms-dashboard'),
        'enabled'      => true,
        'installed_at' => now()->subYear(),
        'created_at'   => now(),
        'updated_at'   => now(),
        ...$overrides,
    ]);
}

beforeEach(function (): void {
    DB::table('addons')->whereIn('registry_id', ['phpvms/dashboard', 'phpvms/widgets'])->delete();
});

it('moves the enabled old row to the new identity and keeps its id', function (): void {
    $id = seedAddonRow([]);

    renameDashboardAddonMigration()->up();

    $row = DB::table('addons')->where('registry_id', 'phpvms/widgets')->sole();

    expect($row->id)->toBe($id)
        ->and((bool) $row->enabled)->toBeTrue()
        ->and($row->namespace)->toBe('Modules\\PhpvmsWidgets')
        ->and($row->path)->toBe(base_path('modules/phpvms-widgets'))
        ->and(DB::table('addons')->where('registry_id', 'phpvms/dashboard')->exists())->toBeFalse();
});

it('merges a disabled row that discovery already added for the new module', function (): void {
    $id = seedAddonRow([]);
    seedAddonRow([
        'name'        => 'PhpvmsWidgets',
        'registry_id' => 'phpvms/widgets',
        'namespace'   => 'Modules\\PhpvmsWidgets',
        'path'        => base_path('modules/phpvms-widgets'),
        'enabled'     => false,
    ]);

    renameDashboardAddonMigration()->up();

    $rows = DB::table('addons')->where('registry_id', 'phpvms/widgets')->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->id)->toBe($id)
        ->and((bool) $rows->first()->enabled)->toBeTrue();
});

it('keeps a disabled old row disabled, and does nothing without one', function (): void {
    renameDashboardAddonMigration()->up();
    expect(DB::table('addons')->where('registry_id', 'phpvms/widgets')->exists())->toBeFalse();

    seedAddonRow(['enabled' => false]);
    renameDashboardAddonMigration()->up();

    expect((bool) DB::table('addons')->where('registry_id', 'phpvms/widgets')->value('enabled'))->toBeFalse();
});
