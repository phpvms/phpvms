<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Models\SimBriefAirframe;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SimBriefAirframePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SimBriefAirframe');
    }

    public function view(AuthUser $authUser, SimBriefAirframe $simBriefAirframe): bool
    {
        return $authUser->can('View:SimBriefAirframe');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SimBriefAirframe');
    }

    public function update(AuthUser $authUser, SimBriefAirframe $simBriefAirframe): bool
    {
        return $authUser->can('Update:SimBriefAirframe');
    }

    public function delete(AuthUser $authUser, SimBriefAirframe $simBriefAirframe): bool
    {
        return $authUser->can('Delete:SimBriefAirframe');
    }

    public function restore(AuthUser $authUser, SimBriefAirframe $simBriefAirframe): bool
    {
        return $authUser->can('Restore:SimBriefAirframe');
    }

    public function forceDelete(AuthUser $authUser, SimBriefAirframe $simBriefAirframe): bool
    {
        return $authUser->can('ForceDelete:SimBriefAirframe');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SimBriefAirframe');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SimBriefAirframe');
    }

    public function replicate(AuthUser $authUser, SimBriefAirframe $simBriefAirframe): bool
    {
        return $authUser->can('Replicate:SimBriefAirframe');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SimBriefAirframe');
    }
}
