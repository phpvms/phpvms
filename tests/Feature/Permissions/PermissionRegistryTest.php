<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\PermissionRegistry;
use Modules\VMSAcars\Models\Rule;

it('exposes three abilities per resource subject', function (): void {
    $registry = app(PermissionRegistry::class);

    $subjects = $registry->resourceSubjects();

    expect($subjects)->toHaveKey('user');
    expect($subjects)->toHaveKey('airline');

    $all = $registry->all();

    expect($all)->toContain('view:user', 'edit:user', 'delete:user');
    expect($all)->toContain('view:airline', 'edit:airline', 'delete:airline');

    // The collapsed model never exposes the old shield abilities.
    expect($all)->not->toContain('view-any:user', 'force-delete:user', 'replicate:user');
});

it('includes core custom permissions', function (): void {
    $all = app(PermissionRegistry::class)->all();

    expect($all)->toContain(
        'view-logs',
        'view:modules',
        'view:dashboard',
        'create-backup',
        'delete-backup',
        'download-backup',
    );
});

it('puts the backup action permissions in the Backups page group', function (): void {
    $backups = collect(app(PermissionRegistry::class)->grouped())
        ->firstWhere('key', 'page:backups');

    expect($backups)->not->toBeNull();

    $names = collect($backups['permissions'])->pluck('name')->all();

    expect($names)->toContain('view:backups', 'create-backup', 'download-backup', 'delete-backup');

    // The standalone custom "Backups" group no longer exists.
    expect(collect(app(PermissionRegistry::class)->grouped())->firstWhere('key', 'custom:Backups'))->toBeNull();
});

it('groups permissions with labels and types', function (): void {
    $grouped = app(PermissionRegistry::class)->grouped();

    $types = collect($grouped)->pluck('type')->unique();
    expect($types)->toContain('resource', 'custom');

    $userGroup = collect($grouped)->firstWhere('key', 'resource:user');
    expect($userGroup)->not->toBeNull();
    expect($userGroup['label'])->toBe('Users');
    expect(collect($userGroup['permissions'])->pluck('ability')->all())
        ->toBe(['view', 'edit', 'delete']);
});

it('attributes app classes to the core scope and module classes to their module', function (): void {
    $registry = app(PermissionRegistry::class);

    expect($registry->moduleOf(User::class))->toBeNull();
    expect($registry->moduleOf(Rule::class))->toBe('VMSAcars');
    expect($registry->moduleOf('Modules\\Awards\\Filament\\Pages\\Foo'))->toBe('Awards');

    expect($registry->moduleKey('VMSAcars'))->toBe('vmsacars');
});

it('scopes core permission groups to the core scope', function (): void {
    $grouped = collect(app(PermissionRegistry::class)->grouped());

    $userGroup = $grouped->firstWhere('key', 'resource:user');
    expect($userGroup['scope_key'])->toBe(PermissionRegistry::CORE_SCOPE);

    // Every group carries a scope key.
    expect($grouped->every(fn (array $group): bool => filled($group['scope_key'])))->toBeTrue();
});

it('scopes an active module and exposes its access permission', function (): void {
    $registry = app(PermissionRegistry::class);
    $sampleGroups = collect($registry->grouped())->where('scope_key', 'sample');

    if ($sampleGroups->isEmpty()) {
        $this->markTestSkipped('Sample module is not active');
    }

    // The module contributes an access permission and a module-access group.
    expect($registry->all())->toContain('access:sample');
    expect($sampleGroups->firstWhere('type', 'module'))->not->toBeNull();
    expect($sampleGroups->firstWhere('type', 'module')['permissions'][0]['name'])->toBe('access:sample');
});

it('lets modules register custom permissions at runtime', function (): void {
    $registry = app(PermissionRegistry::class);
    $registry->register('export-data', 'Reports', 'Export Data');

    expect($registry->all())->toContain('export-data');

    $group = collect($registry->grouped())->firstWhere('key', 'custom:Reports');
    expect($group)->not->toBeNull();
    expect($group['permissions'][0]['name'])->toBe('export-data');
});
