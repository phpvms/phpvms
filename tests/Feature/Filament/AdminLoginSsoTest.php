<?php

declare(strict_types=1);

use App\Models\OAuthConnection;
use App\Models\User;

beforeEach(function (): void {
    User::factory()->create();
});

/**
 * The seeded Discord connection, switched on for whichever surfaces the test
 * needs. Discord is used because its Socialite provider package is installed,
 * which `OAuthConnectionService::enabledFor()` requires before it will offer a
 * connection.
 */
function adminLoginConnection(array $attributes = []): OAuthConnection
{
    $connection = OAuthConnection::query()->where('connection_id', 'discord')->firstOrFail();
    $connection->update([
        'display_name'  => 'Discord Crew',
        'client_id'     => 'client-id',
        'client_secret' => 'client-secret',
        'enabled'       => true,
        'login_enabled' => true,
        ...$attributes,
    ]);

    return $connection->refresh();
}

test('the admin login offers a social sign-in button for an enabled connection', function (): void {
    adminLoginConnection();

    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('Discord Crew')
        ->assertSee(route('oauth.redirect', ['provider' => 'discord', 'intent' => 'login']), escape: false);
});

test('the admin login hides a connection that is not enabled for the login surface', function (): void {
    adminLoginConnection(['login_enabled' => false]);

    $this->get('/admin/login')
        ->assertOk()
        ->assertDontSee('Discord Crew')
        ->assertDontSee(route('oauth.redirect', ['provider' => 'discord', 'intent' => 'login']), escape: false);
});

test('the admin login hides a connection that is disabled outright', function (): void {
    adminLoginConnection(['enabled' => false]);

    $this->get('/admin/login')
        ->assertOk()
        ->assertDontSee('Discord Crew');
});

test('the social sign-in button is not turned into a livewire spa navigation', function (): void {
    adminLoginConnection();

    // The panel runs with spa(), so Filament would otherwise stamp wire:navigate
    // on this same-origin href and fetch() it -- which follows the route's 302
    // out to the identity provider and fails CORS. Asserted against the anchor
    // itself, not the page: "wire:navigate" also appears in Livewire's bundle.
    $html = $this->get('/admin/login')->assertOk()->getContent();

    preg_match_all('/<a\\b[^>]*oauth\\/discord\\/redirect[^>]*>/i', (string) $html, $matches);

    expect($matches[0])->toHaveCount(1)
        ->and($matches[0][0])->not->toContain('wire:navigate');
});

test('a connection can add its own css classes to the social sign-in button', function (): void {
    $connection = adminLoginConnection();
    $connection->update([
        'configuration' => [...($connection->configuration ?? []), 'button_class' => 'bg-brand text-white'],
    ]);

    $html = $this->get('/admin/login')->assertOk()->getContent();

    preg_match_all('/<a\b[^>]*oauth\/discord\/redirect[^>]*>/i', (string) $html, $matches);

    expect($matches[0])->toHaveCount(1)
        ->and($matches[0][0])->toContain('bg-brand text-white')
        // The stock Filament button classes must survive the merge.
        ->and($matches[0][0])->toContain('fi-btn');
});

test('a css class value cannot break out of the button class attribute', function (): void {
    $connection = adminLoginConnection();
    $connection->update([
        'configuration' => [...($connection->configuration ?? []), 'button_class' => 'ok" onclick="alert(1)'],
    ]);

    $html = (string) $this->get('/admin/login')->assertOk()->getContent();

    expect($html)->not->toContain('onclick="alert(1)"');
});
