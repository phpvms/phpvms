<?php

declare(strict_types=1);

use App\Features\OAuth\Helpers\OAuthConnectionService;
use App\Features\OAuth\Helpers\SocialiteProviderRegistry;
use App\Filament\Resources\OAuthConnections\Pages\ManageOAuthConnections;
use App\Models\OAuthConnection;
use App\Models\Permission;
use App\Models\User;
use App\Policies\Filament\OAuthConnectionPolicy;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');

    $this->app->forgetInstance(OAuthConnectionService::class);
    $this->app->instance(
        SocialiteProviderRegistry::class,
        new SocialiteProviderRegistry(
            static fn (string $class): bool => true,
        ),
    );
});

it('authorizes social login connections through their own permission subject', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $policy = new OAuthConnectionPolicy();
    $user = User::factory()->create();
    $connection = new OAuthConnection();

    expect($policy->viewAny($user))->toBeFalse()
        ->and($policy->create($user))->toBeFalse()
        ->and($policy->update($user))->toBeFalse()
        ->and($policy->delete($user))->toBeFalse();

    expect(Permission::query()->where('name', 'view:o-auth-connection')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'edit:o-auth-connection')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'delete:o-auth-connection')->exists())->toBeTrue();

    $user->givePermissionTo('view:o-auth-connection');
    expect($policy->viewAny($user->fresh()))->toBeTrue();

    $user->givePermissionTo('edit:o-auth-connection');
    expect($policy->create($user->fresh()))->toBeTrue()
        ->and($policy->update($user->fresh()))->toBeTrue();

    $user->givePermissionTo('delete:o-auth-connection');
    expect($policy->delete($user->fresh()))->toBeTrue();
});

it('renders the social login page with installed providers and saved connections', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $connection = app(OAuthConnectionService::class)->create(socialLoginConnectionData());

    Livewire::test(ManageOAuthConnections::class)
        ->assertSuccessful()
        ->assertSee('Installed providers: Discord, VATSIM, IVAO, vacentral, OpenID Connect')
        ->assertCanSeeTableRecords([$connection]);
});

it('creates a connection and encrypts its client secret', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    Livewire::test(ManageOAuthConnections::class)
        ->callAction('create', socialLoginConnectionData())
        ->assertHasNoActionErrors();

    $connection = OAuthConnection::query()->where('connection_id', 'crew-login')->firstOrFail();
    $storedSecret = DB::table('oauth_connections')->where('id', $connection->id)->value('client_secret');

    expect($storedSecret)->not->toBe('secret-value')
        ->and($connection->client_secret)->toBe('secret-value')
        ->and($connection->provider)->toBe('openidconnect')
        ->and($connection->configuration['email_claims'])->toBe(['email']);
});

it('shows the callback and OIDC back-channel logout URLs in create and edit drawers', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
    $oidcFields = collect(app(SocialiteProviderRegistry::class)->find('openidconnect')['fields'])
        ->keyBy('key');

    expect($oidcFields['base_url']['label'])->toBe('Issuer Endpoint')
        ->and($oidcFields['email_claims']['default'])->toBe(['email']);

    Livewire::test(ManageOAuthConnections::class)
        ->mountAction('create')
        ->assertSchemaComponentHidden('callback_url', 'mountedActionSchema0')
        ->assertSchemaComponentHidden('backchannel_logout_url', 'mountedActionSchema0')
        ->assertActionDataSet([
            'callback_url'           => url('/oauth/{connection-id}/callback'),
            'backchannel_logout_url' => url('/oauth/{connection-id}/backchannel-logout'),
        ])
        ->set('mountedActions.0.data.protocol', 'oidc')
        ->assertSchemaComponentVisible('callback_url', 'mountedActionSchema0')
        ->assertSchemaComponentVisible('backchannel_logout_url', 'mountedActionSchema0')
        ->assertActionDataSet([
            'configuration' => ['email_claims' => ['email']],
        ])
        ->set('mountedActions.0.data.connection_id', 'crew-login')
        ->assertActionDataSet([
            'callback_url'           => route('oauth.callback', ['provider' => 'crew-login']),
            'backchannel_logout_url' => route('oauth.backchannel-logout', ['provider' => 'crew-login']),
        ])
        ->set('mountedActions.0.data.protocol', 'oauth2')
        ->assertSchemaComponentVisible('callback_url', 'mountedActionSchema0')
        ->assertSchemaComponentHidden('backchannel_logout_url', 'mountedActionSchema0');

    $connection = app(OAuthConnectionService::class)->create(socialLoginConnectionData());

    Livewire::test(ManageOAuthConnections::class)
        ->mountTableAction('edit', $connection)
        ->assertSchemaComponentVisible('callback_url', 'mountedActionSchema0')
        ->assertSchemaComponentVisible('backchannel_logout_url', 'mountedActionSchema0')
        ->assertTableActionDataSet([
            'callback_url'           => route('oauth.callback', ['provider' => 'crew-login']),
            'backchannel_logout_url' => route('oauth.backchannel-logout', ['provider' => 'crew-login']),
        ]);
});

it('creates an OAuth 2.0 connection with the selected provider', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
    OAuthConnection::query()->where('provider', 'discord')->delete();

    Livewire::test(ManageOAuthConnections::class)
        ->callAction('create', socialLoginConnectionData([
            'protocol'      => 'oauth2',
            'provider'      => 'discord',
            'configuration' => [],
            'scopes'        => ['identify'],
        ]))
        ->assertHasNoActionErrors();

    expect(OAuthConnection::query()->where('connection_id', 'crew-login')->firstOrFail()->provider)
        ->toBe('discord');
});

it('rejects invalid and duplicate connection IDs', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    app(OAuthConnectionService::class)->create(socialLoginConnectionData());

    Livewire::test(ManageOAuthConnections::class)
        ->callAction('create', socialLoginConnectionData(['connection_id' => 'Not_Valid']))
        ->assertHasActionErrors(['connection_id']);

    Livewire::test(ManageOAuthConnections::class)
        ->callAction('create', socialLoginConnectionData())
        ->assertHasActionErrors(['connection_id']);

    expect(OAuthConnection::query()->where('connection_id', 'crew-login')->count())->toBe(1);
});

it('does not send a saved client secret into the edit drawer', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $connection = app(OAuthConnectionService::class)->create(socialLoginConnectionData());

    Livewire::test(ManageOAuthConnections::class)
        ->mountTableAction('edit', $connection)
        ->assertTableActionDataSet([
            'client_secret' => null,
            'protocol'      => 'oidc',
        ])
        ->assertDontSee('secret-value');
});

it('limits addon-managed connections to availability controls', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $connection = app(OAuthConnectionService::class)->createManaged(
        socialLoginConnectionData(['enabled' => true]),
        'vacentral-addon',
    );

    Livewire::test(ManageOAuthConnections::class)
        ->callTableAction('edit', $connection, [
            'display_name'         => 'Broken managed name',
            'enabled'              => false,
            'login_enabled'        => false,
            'registration_enabled' => false,
            'linking_enabled'      => false,
            'sort_order'           => 4,
        ])
        ->assertHasNoTableActionErrors();

    $connection->refresh();

    expect($connection->display_name)->toBe('Crew Login')
        ->and($connection->enabled)->toBeFalse()
        ->and($connection->sort_order)->toBe(4);

    Livewire::test(ManageOAuthConnections::class)
        ->assertTableActionDisabled('delete', $connection);
});

/**
 * @return array<string, mixed>
 */
function socialLoginConnectionData(array $overrides = []): array
{
    return [
        ...[
            'connection_id' => 'crew-login',
            'display_name'  => 'Crew Login',
            'protocol'      => 'oidc',
            'provider'      => 'openidconnect',
            'client_id'     => 'client-id',
            'client_secret' => 'secret-value',
            'scopes'        => ['identify', 'email'],
            'configuration' => [
                'base_url'     => 'https://auth.example.com',
                'email_claims' => ['email'],
            ],
            'enabled'              => false,
            'login_enabled'        => false,
            'registration_enabled' => false,
            'linking_enabled'      => true,
            'sort_order'           => 0,
        ],
        ...$overrides,
    ];
}
