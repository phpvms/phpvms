<?php

declare(strict_types=1);

use App\Enums\UserState;
use App\Models\Permission;
use App\Models\User;
use App\Services\PermissionRegistry;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;

/*
 * App\Auth\Grants\ApiKeyGrant — exchanges a per-user api_key at POST /oauth/token
 * for a scoped Passport access token (openspec/changes/api-key-oauth-grant).
 *
 * VMSAcars is not present in this checkout, so scope-filtering scenarios use a
 * throwaway API scope registered via PermissionRegistry::registerApiScope,
 * exactly like ScopeRepositoryTest.php.
 */

beforeEach(function (): void {
    // Capture the base catalog so the throwaway scope below never leaks into
    // Passport's static scope registry beyond this test (restored in afterEach).
    $this->originalScopes = Passport::scopes()->pluck('description', 'id')->all();

    app(PermissionRegistry::class)->registerApiScope('x:test', 'API');

    // PassportServiceProvider::boot() merges registered API scopes into the
    // catalog inside its own booted() callback, which has already run by the
    // time this test's beforeEach fires — so register the throwaway scope in
    // the live catalog directly (mirrors ScopeRepositoryTest.php).
    Passport::tokensCan(array_merge($this->originalScopes, [
        'x:test' => 'Test scope',
    ]));

    // A public client allow-listed for the api_key grant (client-provisioning
    // detail, same as any other grant type — mirrors how an admin would
    // create a client for this grant via the OAuth Clients admin screen).
    $this->client = Passport::client()->newQuery()->forceCreate([
        'name'          => 'Test Client',
        'secret'        => null,
        'redirect_uris' => [],
        'grant_types'   => ['api_key'],
        'revoked'       => false,
    ]);
});

afterEach(function (): void {
    Passport::tokensCan($this->originalScopes);
});

function postApiKeyGrant(array $params): TestResponse
{
    return test()->postJson('/oauth/token', array_merge([
        'grant_type' => 'api_key',
        'client_id'  => test()->client->id,
    ], $params));
}

/**
 * The token response carries no top-level `scope` field (Passport's
 * BearerTokenResponse omits it) — the granted scopes are a JWT claim.
 *
 * @return list<string>
 */
function grantedScopes(string $accessToken): array
{
    [, $payload] = explode('.', $accessToken);
    $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

    return $claims['scopes'];
}

test('a valid api_key with a held scope returns a scoped token', function (): void {
    Permission::firstOrCreate(['name' => 'x:test', 'guard_name' => 'web']);
    $user = User::factory()->create(['state' => UserState::ACTIVE]);
    $user->givePermissionTo('x:test');

    $response = postApiKeyGrant([
        'api_key' => $user->api_key,
        'scope'   => 'x:test',
    ]);

    $response->assertStatus(200);
    expect($response->json('access_token'))->not->toBeEmpty()
        ->and($response->json('token_type'))->toBe('Bearer')
        ->and(grantedScopes($response->json('access_token')))->toContain('x:test');
});

test('the issued token uses the configured long-lived TTL', function (): void {
    $user = User::factory()->create(['state' => UserState::ACTIVE]);

    $response = postApiKeyGrant(['api_key' => $user->api_key]);

    $response->assertStatus(200);
    // ~8 months — well beyond the former 15-day access TTL.
    expect((int) $response->json('expires_in'))->toBeGreaterThan(180 * 86400);
});

test('the grant never issues a refresh token', function (): void {
    $user = User::factory()->create(['state' => UserState::ACTIVE]);

    $response = postApiKeyGrant(['api_key' => $user->api_key]);

    $response->assertStatus(200);

    expect($response->json('refresh_token'))->toBeNull();
});

test('an api_key matching more than one user is rejected generically', function (): void {
    User::factory()->create(['state' => UserState::ACTIVE, 'api_key' => 'dup-key-000000000000000000000000000000']);
    User::factory()->create(['state' => UserState::ACTIVE, 'api_key' => 'dup-key-000000000000000000000000000000']);

    $response = postApiKeyGrant(['api_key' => 'dup-key-000000000000000000000000000000']);

    $response->assertStatus(400);

    expect($response->json('error'))->toBe('invalid_grant');
});

test('a scope the user lacks is dropped from the granted token', function (): void {
    $user = User::factory()->create(['state' => UserState::ACTIVE]);

    $response = postApiKeyGrant([
        'api_key' => $user->api_key,
        'scope'   => 'x:test',
    ]);

    $response->assertStatus(200);

    expect(grantedScopes($response->json('access_token')))->not->toContain('x:test');
});

test('omitting scope applies the configured default scope', function (): void {
    $user = User::factory()->create(['state' => UserState::ACTIVE]);

    $response = postApiKeyGrant([
        'api_key' => $user->api_key,
    ]);

    $response->assertStatus(200);

    expect(grantedScopes($response->json('access_token')))->toContain('user:read');
});

test('an unknown api_key is a generic invalid_grant error', function (): void {
    $response = postApiKeyGrant(['api_key' => 'not-a-real-key']);

    $response->assertStatus(400);
    expect($response->json('error'))->toBe('invalid_grant')
        ->and($response->json('error_description'))->toBe('The user credentials were incorrect.')
        ->and($response->json('error_description'))->not->toContain('not-a-real-key')
        ->and($response->json('error_description'))->not->toContain('api_key');
});

test('a suspended user gets the same generic error as an unknown key', function (): void {
    $user = User::factory()->create(['state' => UserState::SUSPENDED]);

    $response = postApiKeyGrant(['api_key' => $user->api_key]);

    $response->assertStatus(400);
    expect($response->json('error'))->toBe('invalid_grant')
        ->and($response->json('error_description'))->toBe('The user credentials were incorrect.');
});

test('an on-leave user is granted a token like an active user', function (): void {
    $user = User::factory()->create(['state' => UserState::ON_LEAVE]);

    $response = postApiKeyGrant(['api_key' => $user->api_key]);

    $response->assertStatus(200);

    expect($response->json('access_token'))->not->toBeEmpty();
});

test('a missing api_key parameter is a distinct invalid_request error', function (): void {
    $response = test()->postJson('/oauth/token', [
        'grant_type' => 'api_key',
        'client_id'  => $this->client->id,
    ]);

    $response->assertStatus(400);
    expect($response->json('error'))->toBe('invalid_request')
        ->and($response->json('hint'))->toContain('api_key');
});

test('a missing or invalid client_id is rejected before credentials are checked', function (): void {
    $user = User::factory()->create(['state' => UserState::ACTIVE]);

    $response = test()->postJson('/oauth/token', [
        'grant_type' => 'api_key',
        'client_id'  => 'does-not-exist',
        'api_key'    => $user->api_key,
    ]);

    $response->assertStatus(401);

    expect($response->json('error'))->not->toBe('invalid_grant');
});

test('a token issued by the grant enforces scope parity on gated routes', function (): void {
    Permission::firstOrCreate(['name' => 'x:test', 'guard_name' => 'web']);
    $user = User::factory()->create(['state' => UserState::ACTIVE, 'airline_id' => 0]);
    $user->givePermissionTo('x:test');

    $response = postApiKeyGrant([
        'api_key' => $user->api_key,
        'scope'   => 'x:test airlines:read',
    ]);
    $response->assertStatus(200);
    expect(grantedScopes($response->json('access_token')))->toContain('x:test')->toContain('airlines:read');
    $token = $response->json('access_token');

    // Holds airlines:read → passes.
    test()->withHeader('Authorization', 'Bearer '.$token)
        ->get('/api/airlines')
        ->assertStatus(200);

    // Does not hold flights:write-gated prefile route → denied.
    $res = test()->withHeader('Authorization', 'Bearer '.$token)
        ->post('/api/pireps/prefile', []);
    expect($res->status())->toBe(403);
});

test('the legacy x-api-key header still authenticates with full access, unchanged', function (): void {
    $user = User::factory()->create(['state' => UserState::ACTIVE, 'airline_id' => 0]);

    test()->withHeader('x-api-key', $user->api_key)
        ->get('/api/user')
        ->assertStatus(200)
        ->assertJson(['data' => ['id' => $user->id]]);

    test()->withHeader('x-api-key', $user->api_key)
        ->get('/api/airlines')
        ->assertStatus(200);
});
