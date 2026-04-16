<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Models\Module;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ModulePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Module');
    }

    public function view(AuthUser $authUser, Module $module): bool
    {
        return $authUser->can('View:Module');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Module');
    }

    public function update(AuthUser $authUser, Module $module): bool
    {
        return $authUser->can('Update:Module');
    }

    public function delete(AuthUser $authUser, Module $module): bool
    {
        return $authUser->can('Delete:Module');
    }

    public function restore(AuthUser $authUser, Module $module): bool
    {
        return $authUser->can('Restore:Module');
    }

    public function forceDelete(AuthUser $authUser, Module $module): bool
    {
        return $authUser->can('ForceDelete:Module');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Module');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Module');
    }

    public function replicate(AuthUser $authUser, Module $module): bool
    {
        return $authUser->can('Replicate:Module');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Module');
    }
}
