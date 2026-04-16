<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Models\UserField;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class UserFieldPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UserField');
    }

    public function view(AuthUser $authUser, UserField $userField): bool
    {
        return $authUser->can('View:UserField');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UserField');
    }

    public function update(AuthUser $authUser, UserField $userField): bool
    {
        return $authUser->can('Update:UserField');
    }

    public function delete(AuthUser $authUser, UserField $userField): bool
    {
        return $authUser->can('Delete:UserField');
    }

    public function restore(AuthUser $authUser, UserField $userField): bool
    {
        return $authUser->can('Restore:UserField');
    }

    public function forceDelete(AuthUser $authUser, UserField $userField): bool
    {
        return $authUser->can('ForceDelete:UserField');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:UserField');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:UserField');
    }

    public function replicate(AuthUser $authUser, UserField $userField): bool
    {
        return $authUser->can('Replicate:UserField');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:UserField');
    }
}
