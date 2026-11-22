<?php

declare(strict_types=1);

use App\Features\Setup\Support\UpgradeCompleted;
use App\Http\Middleware\UpdatePending;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Support\Facades\DB;

test('a lost completion file still counts as completed once a run recorded it, and is rewritten', function (): void {
    $path = sys_get_temp_dir().'/phpvms-upgrade-completed-'.uniqid();

    try {
        new UpgradeCompleted($path)->write();
        unlink($path);

        expect(new UpgradeCompleted($path)->exists())->toBeTrue()
            ->and(is_file($path))->toBeTrue();
    } finally {
        @unlink($path);
    }
});

test('a lost run file mid-upgrade does not count as completed, though the 8.x tables exist', function (): void {
    // The add-ons table is created partway through the run; only the end of the run records completion.
    expect(DB::table('migrations')->where('migration', '2026_06_04_000001_create_addons_table')->exists())->toBeTrue()
        ->and(new UpgradeCompleted(sys_get_temp_dir().'/phpvms-upgrade-completed-'.uniqid())->exists())->toBeFalse();
});

test('the panel middleware rewrites a lost completion file even when nothing is pending', function (): void {
    $path = sys_get_temp_dir().'/phpvms-upgrade-completed-'.uniqid();
    DB::table('kvp')->insert(['key' => UpgradeCompleted::KVP_KEY, 'value' => now()->toIso8601String()]);
    app()->instance(UpgradeCompleted::class, new UpgradeCompleted($path));
    $this->withMiddleware(UpdatePending::class);
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    try {
        $this->get('/admin');

        expect(is_file($path))->toBeTrue();
    } finally {
        @unlink($path);
    }
});
