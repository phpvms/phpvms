<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Models\Aircraft;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AircraftPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Aircraft');
    }

    public function view(AuthUser $authUser, Aircraft $aircraft): bool
    {
        return $authUser->can('View:Aircraft');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Aircraft');
    }

    public function update(AuthUser $authUser, Aircraft $aircraft): bool
    {
        return $authUser->can('Update:Aircraft');
    }

    public function delete(AuthUser $authUser, Aircraft $aircraft): bool
    {
        return $authUser->can('Delete:Aircraft');
    }

    public function restore(AuthUser $authUser, Aircraft $aircraft): bool
    {
        return $authUser->can('Restore:Aircraft');
    }

    public function forceDelete(AuthUser $authUser, Aircraft $aircraft): bool
    {
        return $authUser->can('ForceDelete:Aircraft');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Aircraft');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Aircraft');
    }

    public function replicate(AuthUser $authUser, Aircraft $aircraft): bool
    {
        return $authUser->can('Replicate:Aircraft');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Aircraft');
    }
}
