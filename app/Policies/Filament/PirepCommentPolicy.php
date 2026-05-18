<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Models\PirepComment;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Comments are a sub-capability of PIREP management — anyone who can view a
 * PIREP can read its comments, anyone who can update a PIREP can comment on
 * it, and anyone who can delete a PIREP can prune comments. Reusing the
 * `pirep` permissions avoids permission-table sprawl and keeps the mental
 * model consistent (no separate "manage comments" role to grant).
 */
class PirepCommentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view-any:pirep');
    }

    public function view(AuthUser $authUser, PirepComment $pirepComment): bool
    {
        return $authUser->can('view:pirep');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('update:pirep');
    }

    public function update(AuthUser $authUser, PirepComment $pirepComment): bool
    {
        return $authUser->can('update:pirep');
    }

    public function delete(AuthUser $authUser, PirepComment $pirepComment): bool
    {
        return $authUser->can('update:pirep');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('update:pirep');
    }

    public function restore(AuthUser $authUser, PirepComment $pirepComment): bool
    {
        return $authUser->can('update:pirep');
    }

    public function forceDelete(AuthUser $authUser, PirepComment $pirepComment): bool
    {
        return $authUser->can('delete:pirep');
    }
}
