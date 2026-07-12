<?php

declare(strict_types=1);

use App\Enums\UserState;
use App\Models\User;
use Laravel\Passport\ClientRepository;

/*
 * Passport OAuth2 API authentication + scope enforcement.
 *
 * Distinct from OAuthTest.php, which covers Socialite social login.
 */

beforeEach(function (): void {
    // Personal access tokens (used for the real-bearer-token assertions) need a
    // personal access client to exist; RefreshDatabase wipes it each test.
    app(ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access Client');
});

// ---------------------------------------------------------------------------
// Authentication (Passport bearer + legacy fallback)
// ---------------------------------------------------------------------------

test('a valid passport bearer token authenticates', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['*'])->accessToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->get('/api/user')
        ->assertStatus(200)
        ->assertJson(['data' => ['id' => $user->id]]);
});

test('an invalid bearer token is rejected', function (): void {
    $this->withHeader('Authorization', 'Bearer not-a-real-token')
        ->get('/api/user')
        ->assertStatus(401);
});

test('the legacy api key still authenticates', function (): void {
    $user = User::factory()->create();

    // Raw Authorization header (no Bearer prefix) and X-API-Key, incl. case variants
    $this->withHeader('Authorization', $user->api_key)->get('/api/user')
        ->assertStatus(200)->assertJson(['data' => ['id' => $user->id]]);
    $this->withHeader('x-api-key', $user->api_key)->get('/api/user')
        ->assertJson(['data' => ['id' => $user->id]]);
    $this->withHeader('X-API-KEY', $user->api_key)->get('/api/user')
        ->assertJson(['data' => ['id' => $user->id]]);
});

test('a missing credential is rejected', function (): void {
    $this->get('/api/user')->assertStatus(401);
});

test('a passport token takes precedence over a legacy key', function (): void {
    $tokenUser = User::factory()->create();
    $legacyUser = User::factory()->create();
    $token = $tokenUser->createToken('test', ['*'])->accessToken;

    // Present BOTH a valid bearer token and a legacy key header. The bearer
    // token must win, so the resolved user is the token's owner.
    $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'X-API-Key'     => $legacyUser->api_key,
    ])->get('/api/user')
        ->assertStatus(200)
        ->assertJson(['data' => ['id' => $tokenUser->id]]);
});

test('an inactive user is denied on both auth paths', function (): void {
    $legacy = User::factory()->create(['state' => UserState::PENDING]);
    $this->withHeader('Authorization', $legacy->api_key)->get('/api/user')->assertStatus(401);

    $tokenUser = User::factory()->create(['state' => UserState::SUSPENDED]);
    $token = $tokenUser->createToken('test', ['*'])->accessToken;
    $this->withHeader('Authorization', 'Bearer '.$token)->get('/api/user')->assertStatus(401);
});

// ---------------------------------------------------------------------------
// Scope enforcement
// ---------------------------------------------------------------------------

test('a token with the required scope is allowed', function (): void {
    $user = User::factory()->create(['airline_id' => 0]);
    apiAsToken($user, ['airlines:read']);

    $this->get('/api/airlines')->assertStatus(200);
});

test('a token missing the required scope gets 403 insufficient_scope', function (): void {
    $user = User::factory()->create(['airline_id' => 0]);
    // Has a read scope but not the write scope the filing route needs.
    apiAsToken($user, ['flights:read']);

    $res = $this->post('/api/pireps/prefile', []);
    $res->assertStatus(403);

    expect($res->json('error.message'))->toContain('insufficient_scope');
});

test('a wildcard token satisfies any scope', function (): void {
    $user = User::factory()->create(['airline_id' => 0]);
    apiAsToken($user, ['*']);

    $this->get('/api/airlines')->assertStatus(200);
    $this->get('/api/user')->assertStatus(200);
});

test('a scoped token cannot reach a route outside its scope', function (): void {
    $user = User::factory()->create(['airline_id' => 0]);
    // user:read only — should not reach airlines:read
    apiAsToken($user, ['user:read']);

    $this->get('/api/user')->assertStatus(200);
    $this->get('/api/airlines')->assertStatus(403);
});

test('a legacy api key reaches scope-protected routes unchanged', function (): void {
    $user = User::factory()->create(['airline_id' => 0]);

    // A read route and a write route, both scope-protected — legacy keys carry
    // full (transient) access and must pass both without a 403.
    apiAs($user);
    $this->get('/api/airlines')->assertStatus(200);

    apiAs($user);
    // 422/other is fine — the point is it is NOT blocked by the scope gate (403).
    expect($this->post('/api/pireps/prefile', [])->status())->not->toBe(403);
});
