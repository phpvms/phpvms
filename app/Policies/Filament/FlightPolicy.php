<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Models\Flight;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class FlightPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Flight');
    }

    public function view(AuthUser $authUser, Flight $flight): bool
    {
        return $authUser->can('View:Flight');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Flight');
    }

    public function update(AuthUser $authUser, Flight $flight): bool
    {
        return $authUser->can('Update:Flight');
    }

    public function delete(AuthUser $authUser, Flight $flight): bool
    {
        return $authUser->can('Delete:Flight');
    }

    public function restore(AuthUser $authUser, Flight $flight): bool
    {
        return $authUser->can('Restore:Flight');
    }

    public function forceDelete(AuthUser $authUser, Flight $flight): bool
    {
        return $authUser->can('ForceDelete:Flight');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Flight');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Flight');
    }

    public function replicate(AuthUser $authUser, Flight $flight): bool
    {
        return $authUser->can('Replicate:Flight');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Flight');
    }
}
