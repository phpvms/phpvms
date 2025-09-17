<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Models\Fare;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class FarePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Fare');
    }

    public function view(AuthUser $authUser, Fare $fare): bool
    {
        return $authUser->can('View:Fare');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Fare');
    }

    public function update(AuthUser $authUser, Fare $fare): bool
    {
        return $authUser->can('Update:Fare');
    }

    public function delete(AuthUser $authUser, Fare $fare): bool
    {
        return $authUser->can('Delete:Fare');
    }

    public function restore(AuthUser $authUser, Fare $fare): bool
    {
        return $authUser->can('Restore:Fare');
    }

    public function forceDelete(AuthUser $authUser, Fare $fare): bool
    {
        return $authUser->can('ForceDelete:Fare');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Fare');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Fare');
    }

    public function replicate(AuthUser $authUser, Fare $fare): bool
    {
        return $authUser->can('Replicate:Fare');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Fare');
    }
}
