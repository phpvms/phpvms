<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\Grants\ApiKeyGrant;
use App\Auth\ScopeRepository;
use App\Services\PermissionRegistry;
use App\Support\ApiScope;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\ScopeRepository as PassportScopeRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\AuthorizationServer;
use Override;

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
    #[Override]
    public function register(): void
    {
        // Gate every catalog scope by the holder's Spatie permission at token
        // issuance (App\Auth\ScopeRepository::finalizeScopes()). Replaces any
        // per-plugin ScopeRepository binding. Bound in register() so it is in
        // place before the AuthorizationServer singleton is first resolved.
        $this->app->bind(PassportScopeRepository::class, ScopeRepository::class);

        // Add the api_key grant lazily — only when Passport actually builds the
        // AuthorizationServer (on a real token request), never at boot. Resolving
        // it eagerly would force makeCryptKey('private') during console/build
        // steps (e.g. package:discover) before the OAuth keys exist. Passport's
        // own grants are enabled inside the singleton builder before this fires,
        // and enableGrantType() keys by identifier, so it is idempotent.
        $this->app->resolving(
            AuthorizationServer::class,
            static function (AuthorizationServer $server): void {
                $server->enableGrantType(new ApiKeyGrant(), Passport::tokensExpireIn());
            },
        );
    }

    public function boot(): void
    {
        // Register the full scope catalog (including the wildcard) so tokens may
        // be issued for any of them and the consent screen can describe them.
        $catalog = [ApiScope::All->value => ApiScope::All->description()] + ApiScope::catalog();
        Passport::tokensCan($catalog);

        // Merge in permission-backed API scopes (App\Services\PermissionRegistry
        // ::registerApiScope()) once every provider — including addons — has
        // booted and declared its scopes, so the merge never races a provider
        // that registers later. Preserves the ApiScope catalog set above.
        $this->app->booted(static function () use ($catalog): void {
            $apiScopeCatalog = app(PermissionRegistry::class)->apiScopeCatalog();

            Passport::tokensCan(array_merge($catalog, $apiScopeCatalog));
        });

        // Least-privilege default: a token that requests no scopes gets only the
        // ability to read its owner's profile. Everything else must be granted
        // explicitly. Legacy api_key auth is unaffected — it bypasses scopes
        // entirely with wildcard access (see CheckApiScope).
        Passport::defaultScopes([ApiScope::UserRead->value]);

        // Token lifetimes. Short access + rotating refresh: a 1-week access token
        // is renewed silently via a 3-month refresh token, which rotates (a fresh
        // 3-month window) on each use — so an active client never re-authenticates,
        // while an idle one (> 3 months) must. A revoked permission drops from the
        // token within a week, since finalizeScopes re-runs on refresh. Personal
        // access tokens (scripts) keep their own longer lifetime.
        Passport::tokensExpireIn(now()->addWeek());
        Passport::refreshTokensExpireIn(now()->addMonths(3));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));

        // Custom consent screen rendered inside the app frontend theme.
        Passport::authorizationView('oauth.authorize');
    }
}
