<?php

declare(strict_types=1);

namespace App\Models;

use Laravel\Passport\Client as PassportClient;

/**
 * Thin Eloquent subclass of Passport's OAuth client, used purely so the
 * Filament admin resource has a first-party App model to bind to (and so the
 * `oauth-client` policy/permission is auto-discovered by the PermissionRegistry
 * — the subject slug is Str::kebab('OauthClient') = 'oauth-client').
 *
 * Passport itself keeps using its own Laravel\Passport\Client for issuing
 * tokens; this class just reads/writes the same `oauth_clients` table.
 */
class OauthClient extends PassportClient
{
    /**
     * Whether this is a public (PKCE) client — no secret.
     */
    public function isPublic(): bool
    {
        return !$this->confidential();
    }
}
