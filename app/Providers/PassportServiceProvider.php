<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\ApiScope;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

/**
 * Wires Laravel Passport into the application: the OAuth scope catalog, token
 * lifetimes, and the custom authorization (consent) screen.
 *
 * Octane note: every call here mutates Passport's static configuration once at
 * worker boot. None of it is per-request mutable state, so it is Octane-safe —
 * there is nothing to reset between requests (mirrors how config is booted).
 *
 * Client secrets are hashed by Passport 13 by default (Client::secret() casts
 * to a hashed string and exposes the plain value only via $client->plainSecret
 * immediately after creation). There is therefore no hashClientSecrets() toggle
 * to enable — the one-time secret reveal in the admin UI relies on plainSecret.
 */
class PassportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register the full scope catalog (including the wildcard) so tokens may
        // be issued for any of them and the consent screen can describe them.
        $catalog = [ApiScope::All->value => ApiScope::All->description()] + ApiScope::catalog();
        Passport::tokensCan($catalog);

        // Least-privilege default: a token that requests no scopes gets only the
        // ability to read its owner's profile. Everything else must be granted
        // explicitly. Legacy api_key auth is unaffected — it bypasses scopes
        // entirely with wildcard access (see CheckApiScope).
        Passport::defaultScopes([ApiScope::UserRead->value]);

        // Token lifetimes. Access tokens are short-lived and refreshable;
        // personal access tokens (used by ACARS/scripts) live longer.
        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));

        // Custom consent screen rendered inside the app frontend theme.
        Passport::authorizationView('oauth.authorize');
    }
}
