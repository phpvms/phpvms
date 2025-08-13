<?php

namespace App\Policies\Filament;

use App\Models\User;
use App\Models\UserField;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserFieldPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_userfield');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, UserField $userField): bool
    {
        return $user->can('view_userfield');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_userfield');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, UserField $userField): bool
    {
        return $user->can('update_userfield');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, UserField $userField): bool
    {
        return $user->can('delete_userfield');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_userfield');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, UserField $userField): bool
    {
        return $user->can('force_delete_userfield');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_userfield');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, UserField $userField): bool
    {
        return $user->can('restore_userfield');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_userfield');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, UserField $userField): bool
    {
        return $user->can('replicate_userfield');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_userfield');
    }
}
