<?php

declare(strict_types=1);

namespace App\Features\Profile\Actions;

use App\Models\User;
use App\Support\ApiScope;

/**
 * Issue a personal access token. Only scopes in the catalog are granted
 * (never the wildcard). Returns the plaintext token, which is shown once.
 */
final class CreatePersonalToken
{
    /**
     * @param list<string> $scopes
     */
    public function handle(User $user, string $name, array $scopes): string
    {
        $scopes = array_values(array_intersect($scopes, array_keys(ApiScope::catalog())));

        return $user->createToken($name, $scopes)->accessToken;
    }
}
