<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Models\Subfleet;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SubfleetPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Subfleet');
    }

    public function view(AuthUser $authUser, Subfleet $subfleet): bool
    {
        return $authUser->can('View:Subfleet');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Subfleet');
    }

    public function update(AuthUser $authUser, Subfleet $subfleet): bool
    {
        return $authUser->can('Update:Subfleet');
    }

    public function delete(AuthUser $authUser, Subfleet $subfleet): bool
    {
        return $authUser->can('Delete:Subfleet');
    }

    public function restore(AuthUser $authUser, Subfleet $subfleet): bool
    {
        return $authUser->can('Restore:Subfleet');
    }

    public function forceDelete(AuthUser $authUser, Subfleet $subfleet): bool
    {
        return $authUser->can('ForceDelete:Subfleet');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Subfleet');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Subfleet');
    }

    public function replicate(AuthUser $authUser, Subfleet $subfleet): bool
    {
        return $authUser->can('Replicate:Subfleet');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Subfleet');
    }
}
