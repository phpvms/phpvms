<?php

declare(strict_types=1);

use App\Models\User;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Support\Facades\Config;
use Laravel\Passport\ClientRepository;

/**
 * The seven-only pages (design D9): unchanged for seven, redirected to their
 * skylight-flow equivalents for a theme that opts in to the SPA data.
 */
function activateSpaDataTheme(): void
{
    Config::set('themes.themes.probe', ['name' => 'probe', 'extends' => null, 'views-path' => 'seven', 'data' => 'spa']);
    Theme::scanThemes();
    updateSetting('general.theme', 'probe');
}

it('redirects the deprecated pages for an opted-in theme', function (string $route, array $params, string $target): void {
    activateSpaDataTheme();

    $this->actingAs(User::factory()->create())
        ->get(route($route, $params))
        ->assertRedirect($target);
})->with([
    'simbrief generate' => ['frontend.simbrief.generate', ['flight_id' => 'F1', 'aircraft_id' => 7], '/ofp/planning?flight_id=F1&aircraft_id=7'],
    'simbrief briefing' => ['frontend.simbrief.briefing', ['id' => 'ofp-1'], '/ofp/briefings/ofp-1'],
    'profile edit'      => ['frontend.profile.edit', ['profile' => 1], '/profile'],
    'connections'       => ['frontend.profile.connections', [], '/profile'],
]);

it('keeps the one-time token across the connections redirect', function (): void {
    activateSpaDataTheme();
    app(ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access Client');
    $this->actingAs(User::factory()->create());

    $this->post(route('frontend.profile.tokens.store'), ['name' => 'Script'])
        ->assertRedirect(route('frontend.profile.connections'));

    $this->get(route('frontend.profile.connections'))
        ->assertRedirect(route('frontend.profile.index'))
        ->assertSessionHas('plain_text_token');
});

it('still renders the pages for seven', function (): void {
    updateSetting('general.theme', 'seven');

    $this->actingAs(User::factory()->create())
        ->get(route('frontend.profile.connections'))
        ->assertOk();
});
