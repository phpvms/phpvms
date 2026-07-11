<?php

declare(strict_types=1);

use App\Filament\Resources\OAuthClients\Pages\CreateOAuthClient;
use App\Filament\Resources\OAuthClients\Pages\ListOAuthClients;
use App\Models\OauthClient;
use App\Models\User;
use App\Policies\Filament\OauthClientPolicy;
use Database\Seeders\RolesPermissionsSeeder;
use Livewire\Livewire;

it('renders the list page for an authorized admin', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    createAdminUser();

    Livewire::test(ListOAuthClients::class)->assertSuccessful();
});

it('authorizes via the oauth-client permission', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $policy = new OauthClientPolicy();

    // A plain user without the permission is denied.
    $user = User::factory()->create();
    expect($policy->viewAny($user))->toBeFalse();

    // Granting the view permission allows it.
    $user->givePermissionTo('view:oauth-client');
    expect($policy->viewAny($user->fresh()))->toBeTrue();

    // Super-admins are allowed via the Gate::before bypass.
    expect(createAdminUser()->can('view:oauth-client'))->toBeTrue();
});

it('creates a confidential authorization-code client with a secret', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    Livewire::test(CreateOAuthClient::class)
        ->fillForm([
            'name'          => 'Confidential App',
            'client_type'   => 'authorization_code',
            'redirect_uris' => ['https://example.com/callback'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $client = OauthClient::query()->where('name', 'Confidential App')->first();
    expect($client)->not->toBeNull()
        ->and($client->confidential())->toBeTrue()
        ->and($client->hasGrantType('authorization_code'))->toBeTrue();
});

it('creates a public PKCE client without a secret', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    Livewire::test(CreateOAuthClient::class)
        ->fillForm([
            'name'          => 'Public SPA',
            'client_type'   => 'pkce',
            'redirect_uris' => ['https://spa.example.com/callback'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $client = OauthClient::query()->where('name', 'Public SPA')->first();
    expect($client)->not->toBeNull()
        ->and($client->confidential())->toBeFalse()
        ->and($client->hasGrantType('authorization_code'))->toBeTrue();
});
