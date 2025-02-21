<?php

namespace App\Policies\Filament;

use App\Models\Airport;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AirportPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_airport');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Airport $airport): bool
    {
        return $user->can('view_airport');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_airport');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Airport $airport): bool
    {
        return $user->can('update_airport');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Airport $airport): bool
    {
        return $user->can('delete_airport');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_airport');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Airport $airport): bool
    {
        return $user->can('force_delete_airport');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_airport');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Airport $airport): bool
    {
        return $user->can('restore_airport');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_airport');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Airport $airport): bool
    {
        return $user->can('replicate_airport');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_airport');
    }
}
