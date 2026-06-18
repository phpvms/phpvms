<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the super-admin role and the full permission catalog.
 *
 * Replaces the removed filament-shield ShieldSeeder. The super-admin role
 * bypasses every check via the Gate::before hook in AppServiceProvider, so it
 * is created without explicit permission grants. No other role is created by
 * default — admins build their own roles from the roles page.
 */
class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Create every resource/page/custom permission from the registry.
        Artisan::call('permission:sync');

        $guard = config('roles.guard', 'web');

        Role::firstOrCreate(['name' => Role::superAdminName(), 'guard_name' => $guard]);
    }
}
