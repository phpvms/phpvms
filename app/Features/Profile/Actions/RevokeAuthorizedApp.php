<?php

declare(strict_types=1);

namespace App\Features\Profile\Actions;

use App\Models\User;

/**
 * Revoke every token an authorized application holds for the pilot.
 */
final class RevokeAuthorizedApp
{
    public function handle(User $user, string $clientId): void
    {
        $user->tokens()
            ->where('client_id', $clientId)
            ->get()
            ->each(fn ($token) => $token->revoke());
    }
}
