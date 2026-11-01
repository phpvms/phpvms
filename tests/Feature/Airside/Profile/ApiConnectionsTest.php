<?php

declare(strict_types=1);

require_once __DIR__.'/../Support.php';

use App\Features\Profile\Livewire\ApiAccess;
use App\Features\Profile\Livewire\ApiConnections;
use App\Models\User;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Livewire\Livewire;

/**
 * 7.10: API access card (key, regenerate) and the ApiConnections drawer
 * (create token once, list, revoke tokens and authorized apps).
 */
beforeEach(function (): void {
    $this->pilot = useAirside();
    $this->withoutVite();
    $this->actingAs($this->pilot);
    app(ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access Client');
});

function authorizedApp(User $user, string $name = 'Flight Tracker'): string
{
    $client = Passport::client()->newQuery()->forceCreate([
        'name'          => $name,
        'secret'        => null,
        'redirect_uris' => ['https://example.test/callback'],
        'grant_types'   => ['authorization_code'],
        'revoked'       => false,
    ]);

    foreach (['one', 'two'] as $suffix) {
        Passport::token()->newQuery()->forceCreate([
            'id'         => $client->id.'-'.$suffix,
            'user_id'    => $user->id,
            'client_id'  => $client->id,
            'name'       => null,
            'scopes'     => ['user:read'],
            'revoked'    => false,
            'expires_at' => now()->addDay(),
        ]);
    }

    return (string) $client->id;
}

it('regenerates the API key', function (): void {
    $old = $this->pilot->api_key;

    Livewire::test(ApiAccess::class)
        ->call('regenerate')
        ->assertDispatched('toast', kind: 'success', text: 'New API key generated!');

    expect($this->pilot->refresh()->api_key)->not->toBe($old);
});

it('creates a token with the chosen scopes and shows its plaintext once', function (): void {
    $component = Livewire::test(ApiConnections::class)
        ->dispatch('api-connections.open')
        ->set('name', 'My ACARS client')
        ->set('scopes', ['user:read', 'not-a-scope'])
        ->call('createToken')
        ->assertHasNoErrors()
        ->assertSet('name', '')
        ->assertSet('scopes', []);

    $plain = $component->get('plainTextToken');
    expect($plain)->toBeString()->not->toBeEmpty();

    $token = $this->pilot->tokens()->first();
    expect($token->name)->toBe('My ACARS client')
        ->and($token->scopes)->toBe(['user:read']);

    // Closing the drawer drops the plaintext; opening it again does not bring it back.
    $component->set('open', false)->assertSet('plainTextToken', null)
        ->dispatch('api-connections.open')
        ->assertSet('plainTextToken', null);
});

it('requires a token name', function (): void {
    Livewire::test(ApiConnections::class)
        ->dispatch('api-connections.open')
        ->set('name', '')
        ->call('createToken')
        ->assertHasErrors(['name' => 'required'])
        ->assertSet('plainTextToken', null);

    expect($this->pilot->tokens()->count())->toBe(0);
});

it('revokes a personal token', function (): void {
    $this->pilot->createToken('Script', []);
    $tokenId = $this->pilot->tokens()->first()->id;

    Livewire::test(ApiConnections::class)
        ->dispatch('api-connections.open')
        ->call('revokeToken', $tokenId)
        ->assertDispatched('toast', kind: 'success', text: 'Token revoked.');

    expect($this->pilot->tokens()->where('revoked', false)->count())->toBe(0);
});

it("cannot revoke another pilot's token", function (): void {
    $other = User::factory()->create();
    $other->createToken('Theirs', []);

    $theirs = $other->tokens()->first();

    Livewire::test(ApiConnections::class)
        ->dispatch('api-connections.open')
        ->call('revokeToken', $theirs->id);

    expect($theirs->refresh()->revoked)->toBeFalse();
});

it("revokes all of an app's tokens", function (): void {
    $clientId = authorizedApp($this->pilot);

    Livewire::test(ApiConnections::class)
        ->dispatch('api-connections.open')
        ->call('revokeApp', $clientId)
        ->assertDispatched('toast', kind: 'success', text: 'Application access revoked.');

    expect($this->pilot->tokens()->where('client_id', $clientId)->where('revoked', false)->count())->toBe(0);
});
