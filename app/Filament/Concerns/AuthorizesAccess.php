<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Enums\Ability;
use Filament\Facades\Filament;

/**
 * Page authorization for phpVMS. Replaces filament-shield's HasPageShield.
 *
 * A page is accessible when the user holds `view:{key}`. Pages with a mutating
 * action should also declare Ability::Edit and gate that action on canEdit().
 */
trait AuthorizesAccess
{
    use HasPermissionKey;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user?->can(Ability::View->permission(static::getPermissionKey())) ?? false;
    }

    /**
     * Whether the current user may perform the page's mutating action.
     */
    public static function canEdit(): bool
    {
        $user = Filament::auth()->user();

        return $user?->can(Ability::Edit->permission(static::getPermissionKey())) ?? false;
    }
}
