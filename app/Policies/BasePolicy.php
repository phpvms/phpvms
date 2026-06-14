<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Ability;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Shared base policy that maps every Filament policy method onto one of the
 * three phpVMS abilities (view/edit/delete) for a declared subject slug.
 *
 * Concrete policies only declare their `$subject`; super-admins bypass all of
 * these via the `Gate::before` hook registered in AppServiceProvider.
 */
abstract class BasePolicy
{
    use HandlesAuthorization;

    /**
     * The subject slug these permissions are named after, e.g. `user`,
     * `pirep-field`. Permission names resolve to `{ability}:{subject}`.
     */
    protected string $subject = '';

    public function viewAny(AuthUser $user): bool
    {
        return $this->allows($user, 'viewAny');
    }

    public function view(AuthUser $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(AuthUser $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(AuthUser $user): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(AuthUser $user): bool
    {
        return $this->allows($user, 'delete');
    }

    public function deleteAny(AuthUser $user): bool
    {
        return $this->allows($user, 'deleteAny');
    }

    public function restore(AuthUser $user): bool
    {
        return $this->allows($user, 'restore');
    }

    public function restoreAny(AuthUser $user): bool
    {
        return $this->allows($user, 'restoreAny');
    }

    public function forceDelete(AuthUser $user): bool
    {
        return $this->allows($user, 'forceDelete');
    }

    public function forceDeleteAny(AuthUser $user): bool
    {
        return $this->allows($user, 'forceDeleteAny');
    }

    public function replicate(AuthUser $user): bool
    {
        return $this->allows($user, 'replicate');
    }

    public function reorder(AuthUser $user): bool
    {
        return $this->allows($user, 'reorder');
    }

    /**
     * Resolve the policy method to its ability and check the permission.
     */
    protected function allows(AuthUser $user, string $method): bool
    {
        $ability = Ability::policyMethodMap()[$method] ?? null;

        if ($ability === null) {
            return false;
        }

        return $user->can($ability->permission($this->subject));
    }
}
