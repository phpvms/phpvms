<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Models\Typerating;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TyperatingPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Typerating');
    }

    public function view(AuthUser $authUser, Typerating $typerating): bool
    {
        return $authUser->can('View:Typerating');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Typerating');
    }

    public function update(AuthUser $authUser, Typerating $typerating): bool
    {
        return $authUser->can('Update:Typerating');
    }

    public function delete(AuthUser $authUser, Typerating $typerating): bool
    {
        return $authUser->can('Delete:Typerating');
    }

    public function restore(AuthUser $authUser, Typerating $typerating): bool
    {
        return $authUser->can('Restore:Typerating');
    }

    public function forceDelete(AuthUser $authUser, Typerating $typerating): bool
    {
        return $authUser->can('ForceDelete:Typerating');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Typerating');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Typerating');
    }

    public function replicate(AuthUser $authUser, Typerating $typerating): bool
    {
        return $authUser->can('Replicate:Typerating');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Typerating');
    }
}
