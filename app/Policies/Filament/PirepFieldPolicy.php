<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Models\PirepField;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PirepFieldPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PirepField');
    }

    public function view(AuthUser $authUser, PirepField $pirepField): bool
    {
        return $authUser->can('View:PirepField');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PirepField');
    }

    public function update(AuthUser $authUser, PirepField $pirepField): bool
    {
        return $authUser->can('Update:PirepField');
    }

    public function delete(AuthUser $authUser, PirepField $pirepField): bool
    {
        return $authUser->can('Delete:PirepField');
    }

    public function restore(AuthUser $authUser, PirepField $pirepField): bool
    {
        return $authUser->can('Restore:PirepField');
    }

    public function forceDelete(AuthUser $authUser, PirepField $pirepField): bool
    {
        return $authUser->can('ForceDelete:PirepField');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PirepField');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PirepField');
    }

    public function replicate(AuthUser $authUser, PirepField $pirepField): bool
    {
        return $authUser->can('Replicate:PirepField');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PirepField');
    }
}
