<?php

namespace App\Policies\Filament;

use App\Models\Fare;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FarePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_fare');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Fare $fare): bool
    {
        return $user->can('view_fare');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_fare');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Fare $fare): bool
    {
        return $user->can('update_fare');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Fare $fare): bool
    {
        return $user->can('delete_fare');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_fare');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Fare $fare): bool
    {
        return $user->can('force_delete_fare');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_fare');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Fare $fare): bool
    {
        return $user->can('restore_fare');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_fare');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Fare $fare): bool
    {
        return $user->can('replicate_fare');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_fare');
    }
}
