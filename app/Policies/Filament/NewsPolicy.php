<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Models\News;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class NewsPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view-any:news');
    }

    public function view(AuthUser $authUser, News $news): bool
    {
        return $authUser->can('view:news');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create:news');
    }

    public function update(AuthUser $authUser, News $news): bool
    {
        return $authUser->can('update:news');
    }

    public function delete(AuthUser $authUser, News $news): bool
    {
        return $authUser->can('delete:news');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete-any:news');
    }

    public function restore(AuthUser $authUser, News $news): bool
    {
        return $authUser->can('restore:news');
    }

    public function forceDelete(AuthUser $authUser, News $news): bool
    {
        return $authUser->can('force-delete:news');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force-delete-any:news');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore-any:news');
    }

    public function replicate(AuthUser $authUser, News $news): bool
    {
        return $authUser->can('replicate:news');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder:news');
    }
}
