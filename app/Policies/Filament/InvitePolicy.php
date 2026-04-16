<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Models\Invite;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class InvitePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Invite');
    }

    public function view(AuthUser $authUser, Invite $invite): bool
    {
        return $authUser->can('View:Invite');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Invite');
    }

    public function update(AuthUser $authUser, Invite $invite): bool
    {
        return $authUser->can('Update:Invite');
    }

    public function delete(AuthUser $authUser, Invite $invite): bool
    {
        return $authUser->can('Delete:Invite');
    }

    public function restore(AuthUser $authUser, Invite $invite): bool
    {
        return $authUser->can('Restore:Invite');
    }

    public function forceDelete(AuthUser $authUser, Invite $invite): bool
    {
        return $authUser->can('ForceDelete:Invite');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Invite');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Invite');
    }

    public function replicate(AuthUser $authUser, Invite $invite): bool
    {
        return $authUser->can('Replicate:Invite');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Invite');
    }
}
