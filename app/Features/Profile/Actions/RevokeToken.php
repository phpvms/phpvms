<?php

declare(strict_types=1);

namespace App\Features\Profile\Actions;

use App\Models\User;

/**
 * Revoke one of the pilot's own tokens. Another pilot's token id is ignored.
 */
final class RevokeToken
{
    public function handle(User $user, string $tokenId): void
    {
        $user->tokens()->where('id', $tokenId)->first()?->revoke();
    }
}
