<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Models\Pirep;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PirepPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Pirep');
    }

    public function view(AuthUser $authUser, Pirep $pirep): bool
    {
        return $authUser->can('View:Pirep');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Pirep');
    }

    public function update(AuthUser $authUser, Pirep $pirep): bool
    {
        return $authUser->can('Update:Pirep');
    }

    public function delete(AuthUser $authUser, Pirep $pirep): bool
    {
        return $authUser->can('Delete:Pirep');
    }

    public function restore(AuthUser $authUser, Pirep $pirep): bool
    {
        return $authUser->can('Restore:Pirep');
    }

    public function forceDelete(AuthUser $authUser, Pirep $pirep): bool
    {
        return $authUser->can('ForceDelete:Pirep');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Pirep');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Pirep');
    }

    public function replicate(AuthUser $authUser, Pirep $pirep): bool
    {
        return $authUser->can('Replicate:Pirep');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Pirep');
    }
}
