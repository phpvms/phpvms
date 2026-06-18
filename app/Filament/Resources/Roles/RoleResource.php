<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles;

use App\Enums\NavigationGroup as EnumsNavigationGroup;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Filament\Resources\Roles\Tables\RolesTable;
use App\Models\Permission;
use App\Models\Role;
use App\Services\PermissionRegistry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = EnumsNavigationGroup::Config;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'roles';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index'  => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit'   => EditRole::route('/{record}/edit'),
        ];
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return trans_choice('common.role', 1);
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return trans_choice('common.role', 2);
    }

    /**
     * The grouped permission catalog rendered as the matrix.
     *
     * @return list<array{key: string, label: string, type: string, scope: string, scope_key: string, permissions: list<array{name: string, ability: ?string, label: string}>}>
     */
    public static function permissionGroups(): array
    {
        return app(PermissionRegistry::class)->grouped();
    }

    /**
     * A form-safe field key for a group (colons/dashes break dot-notation).
     */
    public static function safeKey(string $key): string
    {
        return str_replace([':', '-'], '_', $key);
    }

    /**
     * Flatten the per-group matrix state into a unique list of permission names.
     *
     * @param  array<string, array<int, string>> $grouped
     * @return list<string>
     */
    public static function flattenPermissions(array $grouped): array
    {
        return collect($grouped)
            ->flatten()
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Sync the selected permission names onto the role, first creating any that
     * the registry knows about but the database is missing.
     *
     * The matrix offers registry-derived names, but `permission:sync` runs in a
     * console context that can enumerate a different set of module Filament
     * components than a web request. Creating the selected, registry-valid
     * permissions here keeps the save resilient to that discrepancy instead of
     * throwing PermissionDoesNotExist. Names not known to the registry are
     * dropped rather than persisted.
     *
     * @param list<string> $names
     */
    public static function syncRolePermissions(Role $role, array $names): void
    {
        $known = array_flip(app(PermissionRegistry::class)->all());
        $guard = config('roles.guard', 'web');

        $valid = array_values(array_filter($names, static fn (string $name): bool => isset($known[$name])));

        foreach ($valid as $name) {
            // Creating a missing Permission flushes the spatie permission cache,
            // so the syncPermissions() lookup below resolves it.
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }

        $role->syncPermissions($valid);
    }

    /**
     * Whether the given role is the protected super-admin role.
     */
    public static function isSuperAdmin(?Role $role): bool
    {
        return $role instanceof Role && $role->name === Role::superAdminName();
    }
}
