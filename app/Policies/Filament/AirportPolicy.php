<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Models\Airport;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AirportPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Airport');
    }

    public function view(AuthUser $authUser, Airport $airport): bool
    {
        return $authUser->can('View:Airport');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Airport');
    }

    public function update(AuthUser $authUser, Airport $airport): bool
    {
        return $authUser->can('Update:Airport');
    }

    public function delete(AuthUser $authUser, Airport $airport): bool
    {
        return $authUser->can('Delete:Airport');
    }

    public function restore(AuthUser $authUser, Airport $airport): bool
    {
        return $authUser->can('Restore:Airport');
    }

    public function forceDelete(AuthUser $authUser, Airport $airport): bool
    {
        return $authUser->can('ForceDelete:Airport');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Airport');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Airport');
    }

    public function replicate(AuthUser $authUser, Airport $airport): bool
    {
        return $authUser->can('Replicate:Airport');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Airport');
    }
}
