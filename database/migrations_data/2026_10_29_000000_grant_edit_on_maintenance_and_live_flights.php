<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Data migration: the Maintenance and Live Flights pages now gate their
 * mutating actions on `edit:{page}` instead of letting `view:{page}` run them.
 *
 * Grant the new edit permission to every role and user that already holds the
 * view permission, so nobody loses an action they could run before. Admins
 * can then take it away from view-only roles.
 *
 * Idempotent: givePermissionTo() skips a permission the holder already has.
 */
return new class() extends Migration
{
    /**
     * @var list<string>
     */
    private const array PAGES = ['maintenance', 'live-flights'];

    public function up(): void
    {
        $guard = config('roles.guard', 'web');

        foreach (self::PAGES as $page) {
            $view = Permission::where(['name' => 'view:'.$page, 'guard_name' => $guard])->first();
            if ($view === null) {
                continue;
            }

            $edit = Permission::firstOrCreate(['name' => 'edit:'.$page, 'guard_name' => $guard]);

            foreach ($view->roles as $role) {
                $role->givePermissionTo($edit);
            }

            foreach ($view->users as $user) {
                $user->givePermissionTo($edit);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
