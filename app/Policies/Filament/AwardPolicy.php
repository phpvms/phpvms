<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Models\Award;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AwardPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Award');
    }

    public function view(AuthUser $authUser, Award $award): bool
    {
        return $authUser->can('View:Award');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Award');
    }

    public function update(AuthUser $authUser, Award $award): bool
    {
        return $authUser->can('Update:Award');
    }

    public function delete(AuthUser $authUser, Award $award): bool
    {
        return $authUser->can('Delete:Award');
    }

    public function restore(AuthUser $authUser, Award $award): bool
    {
        return $authUser->can('Restore:Award');
    }

    public function forceDelete(AuthUser $authUser, Award $award): bool
    {
        return $authUser->can('ForceDelete:Award');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Award');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Award');
    }

    public function replicate(AuthUser $authUser, Award $award): bool
    {
        return $authUser->can('Replicate:Award');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Award');
    }
}
