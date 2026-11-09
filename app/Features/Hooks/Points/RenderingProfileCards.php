<?php

declare(strict_types=1);

namespace App\Features\Hooks\Points;

use App\Features\Hooks\RenderHook;
use App\Http\Data\ProfileData;

/**
 * Render hook for the pilot profile page, among the profile cards.
 *
 * `$profile` is the profile DTO, including `isOwnProfile`.
 */
final class RenderingProfileCards extends RenderHook
{
    public function __construct(public readonly ProfileData $profile)
    {
        parent::__construct();
    }

    public static function name(): string
    {
        return 'profile.cards';
    }
}
