<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Concerns\PutsPrimaryActionLast;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

class EditRole extends EditRecord
{
    use PutsPrimaryActionLast;

    protected static string $resource = RoleResource::class;

    /**
     * Permission names selected in the matrix, stashed before the model save.
     *
     * @var list<string>
     */
    protected array $selectedPermissions = [];

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn (Role $record): bool => RoleResource::isSuperAdmin($record)),
        ];
    }

    /**
     * Bucket the role's current permissions into the matrix groups.
     */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $role = $this->record;

        // Explicit query avoids the non-prod lazy-loading guard.
        $names = $role instanceof Role ? $role->permissions()->pluck('name')->all() : [];

        $data['permissions'] = [];

        foreach (RoleResource::permissionGroups() as $group) {
            $groupNames = array_column($group['permissions'], 'name');

            $data['permissions'][RoleResource::safeKey($group['key'])] = array_values(
                array_intersect($names, $groupNames)
            );
        }

        return $data;
    }

    #[Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->selectedPermissions = RoleResource::flattenPermissions($data['permissions'] ?? []);
        unset($data['permissions']);

        return $data;
    }

    protected function afterSave(): void
    {
        $role = $this->record;

        // The super-admin role's matrix is hidden; never wipe its grants.
        if (!$role instanceof Role || RoleResource::isSuperAdmin($role)) {
            return;
        }

        RoleResource::syncRolePermissions($role, $this->selectedPermissions);
    }
}
