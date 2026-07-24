<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreateRole extends CreateRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = RoleResource::class;

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }

    /**
     * Permission names selected in the matrix, stashed before the model save.
     *
     * @var list<string>
     */
    protected array $selectedPermissions = [];

    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->selectedPermissions = RoleResource::flattenPermissions($data['permissions'] ?? []);
        unset($data['permissions']);

        $data['guard_name'] = config('roles.guard', 'web');

        return $data;
    }

    protected function afterCreate(): void
    {
        $role = $this->record;

        if ($role instanceof Role) {
            RoleResource::syncRolePermissions($role, $this->selectedPermissions);
        }
    }
}
