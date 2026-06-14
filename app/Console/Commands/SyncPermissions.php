<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Permission;
use App\Services\PermissionRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'permission:sync', description: 'Sync permissions from the registry into the database')]
#[Description('Sync permissions from the registry into the database')]
#[Signature('permission:sync {--prune : Delete permissions that are no longer in the registry}')]
class SyncPermissions extends Command
{
    public function handle(PermissionRegistry $registry): int
    {
        $guard = config('roles.guard', 'web');
        $registered = $registry->all();

        $created = 0;

        foreach ($registered as $name) {
            $permission = Permission::firstOrCreate([
                'name'       => $name,
                'guard_name' => $guard,
            ]);

            if ($permission->wasRecentlyCreated) {
                $created++;
            }
        }

        $pruned = 0;

        if ($this->option('prune')) {
            $stale = Permission::whereNotIn('name', $registered)->get();

            foreach ($stale as $permission) {
                $permission->delete();
                $pruned++;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info('Permissions synced. Created: '.$created.($this->option('prune') ? ', Pruned: '.$pruned : ''));

        return self::SUCCESS;
    }
}
