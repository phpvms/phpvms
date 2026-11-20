<?php

declare(strict_types=1);

use App\Features\Setup\Support\UpgradeCompleted;
use App\Http\Middleware\UpdatePending;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Support\Facades\DB;

test('a missing completion file on an 8.x database still counts as completed and is rewritten', function (): void {
    $path = sys_get_temp_dir().'/phpvms-upgrade-completed-'.uniqid();
    expect(DB::table('migrations')->where('migration', '2026_06_04_000001_create_addons_table')->exists())->toBeTrue();

    try {
        expect(new UpgradeCompleted($path)->exists())->toBeTrue()
            ->and(is_file($path))->toBeTrue();
    } finally {
        @unlink($path);
    }
});

test('the panel middleware rewrites a lost completion file even when nothing is pending', function (): void {
    $path = sys_get_temp_dir().'/phpvms-upgrade-completed-'.uniqid();
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
