<?php

declare(strict_types=1);

use App\Models\Permission;

it('creates missing permissions from the registry', function (): void {
    expect(Permission::where('name', 'view:user')->exists())->toBeFalse();

    $this->artisan('permission:sync')->assertSuccessful();

    expect(Permission::where('name', 'view:user')->exists())->toBeTrue();
    expect(Permission::where('name', 'edit:user')->exists())->toBeTrue();
    expect(Permission::where('name', 'delete:user')->exists())->toBeTrue();
    expect(Permission::where('name', 'view-logs')->exists())->toBeTrue();
});

it('is idempotent on a second run', function (): void {
    $this->artisan('permission:sync')->assertSuccessful();
    $count = Permission::count();

    $this->artisan('permission:sync')->assertSuccessful();

    expect(Permission::count())->toBe($count);
});

it('prunes permissions absent from the registry only with --prune', function (): void {
    $this->artisan('permission:sync')->assertSuccessful();

    Permission::create(['name' => 'view:obsolete-thing', 'guard_name' => 'web']);

    // Without --prune the stale permission survives.
    $this->artisan('permission:sync')->assertSuccessful();
    expect(Permission::where('name', 'view:obsolete-thing')->exists())->toBeTrue();

    // With --prune it is removed, registry permissions retained.
    $this->artisan('permission:sync --prune')->assertSuccessful();
    expect(Permission::where('name', 'view:obsolete-thing')->exists())->toBeFalse();
    expect(Permission::where('name', 'view:user')->exists())->toBeTrue();
});
