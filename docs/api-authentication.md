# API Authentication

phpVMS authenticates the API two ways, checked in this order by the `api.auth`
middleware (`App\Http\Middleware\ApiAuth`):

1. **OAuth2 bearer token** (Laravel Passport) — `Authorization: Bearer <token>`
2. **Legacy API key** (fallback) — the per-user `api_key`, sent raw in
   `Authorization` or in `X-API-Key`

Both paths enforce the same user-state gate (only `ACTIVE`/`ON_LEAVE` users) and
return the same error shapes. **Legacy keys keep full access** and are not going
away in this release — but scoped OAuth tokens are the recommended path forward.

## Scopes

Every authenticated route requires a scope. Tokens are limited to what they were
granted; a token missing the required scope gets `403 insufficient_scope`.

| Scope            | Grants                                            |
| ---------------- | ------------------------------------------------- |
| `airlines:read`  | Read airlines                                     |
| `airports:read`  | Read airports, lookup, distance, hubs, search     |
| `fleet:read`     | Read fleet, subfleets and aircraft                |
| `flights:read`   | Read flights, schedules, briefings, routes        |
| `pireps:read`    | Read PIREPs, fields, finances                     |
| `pireps:write`   | File/update/cancel PIREPs and send ACARS data     |
| `user:read`      | Read your profile, fleet, PIREPs and bids         |
| `bids:write`     | Create and remove bids                            |
| `settings:write` | Update account settings (e.g. SimBrief username)  |
| `*`              | Full access (what a legacy API key is treated as) |

The catalog is defined once in `App\Support\ApiScope` and registered with
Passport in `App\Providers\PassportServiceProvider`.

## Personal access tokens (recommended for ACARS / scripts)

Users create scoped personal access tokens from **Profile → Connections**. The
token value is shown once at creation. Pick only the scopes you need — for an
ACARS client that files flights, `pireps:write` (plus `flights:read`,
`user:read`) is typical.

Use it as a bearer token:

```
Authorization: Bearer <personal-access-token>
```

Tokens can be revoked at any time from the same screen without affecting your
other tokens or your legacy API key.

## Third-party applications (authorization code + PKCE)

Third-party apps use the standard OAuth2 authorization-code flow:

- **Confidential clients** (server-side apps) authenticate with a client secret.
- **Public clients** (mobile / SPA) use **PKCE** and have no secret.

Admins register clients in the admin panel under **Developers → OAuth Clients**,
choosing the client type, redirect URIs and grant type. Client secrets are
stored hashed and shown **once** at creation (or rotation) — copy them then.

Users authorize an app via the consent screen at `/oauth/authorize` and can
review or revoke authorized apps from **Profile → Connections**.

## Legacy API key (deprecated)

The legacy `api_key` still works and is still shown (and regenerable) on the
profile page. Prefer a scoped personal access token instead. A future release
will phase the raw key out.

## Deployment notes

- Run `php artisan migrate` to create the `oauth_*` tables.
- Provide Passport keys: `php artisan passport:keys` for a single server, or set
  `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` for multi-node / Octane
  deployments (see `.env.example`).
- Ensure a personal-access client exists (seeded by `DatabaseSeeder`, or
  `php artisan passport:client --personal`).
