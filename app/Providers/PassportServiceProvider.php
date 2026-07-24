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

        // Register the api_key grant (App\Auth\Grants\ApiKeyGrant) once the
        // AuthorizationServer singleton exists, so it is added after
        // Passport's own grants build inside that binding's closure.
        // enableGrantType() keys grants by identifier ('api_key'), so
        // re-registration is idempotent — safe under Octane.
        $this->app->booted(static function (): void {
            $server = app(AuthorizationServer::class);
            $server->enableGrantType(new ApiKeyGrant(), Passport::tokensExpireIn());
        });

        // Least-privilege default: a token that requests no scopes gets only the
        // ability to read its owner's profile. Everything else must be granted
        // explicitly. Legacy api_key auth is unaffected — it bypasses scopes
        // entirely with wildcard access (see CheckApiScope).
        Passport::defaultScopes([ApiScope::UserRead->value]);

        // Token lifetimes. phpVMS's OAuth is API-only (the web frontend uses
        // session auth), so access tokens serve long-lived desktop/ACARS clients
        // — issued for ~8 months across all grants (device flow, api_key, etc.).
        Passport::tokensExpireIn(now()->addMonths(8));
        Passport::refreshTokensExpireIn(now()->addMonths(9));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));

        // Custom consent screen rendered inside the app frontend theme.
        Passport::authorizationView('oauth.authorize');
    }
}
