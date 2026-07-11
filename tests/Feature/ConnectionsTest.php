<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Token;

beforeEach(function (): void {
    // Personal access tokens need a personal access client to exist.
    app(ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access Client');
});

it('renders the connections page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('frontend.profile.connections'))
        ->assertOk();
});

it('lets a user create a scoped personal access token', function (): void {
    $user = User::factory()->create();

    $res = $this->actingAs($user)->post(route('frontend.profile.tokens.store'), [
        'name'   => 'My Script',
        'scopes' => ['flights:read', 'pireps:read'],
    ]);

    $res->assertRedirect(route('frontend.profile.connections'));
    $res->assertSessionHas('plain_text_token');

    $token = $user->tokens()->first();
    expect($token)->not->toBeNull()
        ->and($token->name)->toBe('My Script')
        ->and($token->scopes)->toEqualCanonicalizing(['flights:read', 'pireps:read']);
});

it('ignores scopes that are not in the catalog', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('frontend.profile.tokens.store'), [
        'name'   => 'Sneaky',
        'scopes' => ['flights:read', '*', 'not:a:scope'],
    ]);

    expect($user->tokens()->first()->scopes)->toEqual(['flights:read']);
});

it('lets a user revoke a personal access token', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('frontend.profile.tokens.store'), [
        'name'   => 'Revoke me',
        'scopes' => ['user:read'],
    ]);

    $token = $user->tokens()->first();

    $this->actingAs($user)
        ->delete(route('frontend.profile.tokens.destroy', $token->id))
        ->assertRedirect(route('frontend.profile.connections'));

    expect($token->fresh()->revoked)->toBeTrue();
});

it('lets a user revoke an authorized third-party application', function (): void {
    $user = User::factory()->create();

    $client = app(ClientRepository::class)
        ->createAuthorizationCodeGrantClient('Third Party App', ['https://example.com/callback']);

    // Simulate an issued token for this user tied to the third-party client.
    $token = Token::query()->forceCreate([
        'id'        => Str::random(80),
        'user_id'   => $user->id,
        'client_id' => $client->getKey(),
        'name'      => null,
        'scopes'    => ['flights:read'],
        'revoked'   => false,
    ]);

    // It shows up on the connections page.
    $this->actingAs($user)->get(route('frontend.profile.connections'))
        ->assertOk()
        ->assertSee('Third Party App');

    // Revoking the app revokes its tokens for this user.
    $this->actingAs($user)
        ->delete(route('frontend.profile.connections.destroy', $client->getKey()))
        ->assertRedirect(route('frontend.profile.connections'));

    expect($token->fresh()->revoked)->toBeTrue();
});

it('still lets a user regenerate the legacy api key', function (): void {
    $user = User::factory()->create();
    $old = $user->api_key;

    $this->actingAs($user)
        ->get(route('frontend.profile.regen_apikey'))
        ->assertRedirect();

    expect($user->fresh()->api_key)->not->toBe($old);
});
