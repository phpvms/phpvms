<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Models\Airline;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AirlinePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Airline');
    }

    public function view(AuthUser $authUser, Airline $airline): bool
    {
        return $authUser->can('View:Airline');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Airline');
    }

    public function update(AuthUser $authUser, Airline $airline): bool
    {
        return $authUser->can('Update:Airline');
    }

    public function delete(AuthUser $authUser, Airline $airline): bool
    {
        return $authUser->can('Delete:Airline');
    }

    public function restore(AuthUser $authUser, Airline $airline): bool
    {
        return $authUser->can('Restore:Airline');
    }

    public function forceDelete(AuthUser $authUser, Airline $airline): bool
    {
        return $authUser->can('ForceDelete:Airline');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Airline');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Airline');
    }

    public function replicate(AuthUser $authUser, Airline $airline): bool
    {
        return $authUser->can('Replicate:Airline');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Airline');
    }
}
