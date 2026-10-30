<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Models\Aircraft;
use App\Models\Subfleet;
use App\Models\User;
use Illuminate\Support\Facades\View;

/**
 * 9.1: every view name a frontend or auth controller returns, plus Passport's
 * authorization view, resolves with airside active.
 *
 * The names are read from the controller source, so a view added to a
 * controller later fails here until airside has it. The deprecated seven-only
 * flows redirect for airside (4.1), so their views are excluded: the SimBrief
 * form/aircraft/username/briefing pages (simbrief.generate, simbrief.briefing),
 * profile.edit and profile.connections.
 */
beforeEach(function (): void {
    useAirside();
    $this->withoutVite();
});

/**
 * @return list<string>
 */
function airsideControllerViewNames(): array
{
    $names = ['oauth.authorize'];

    foreach (['Frontend', 'Auth'] as $directory) {
        foreach (glob(dirname(__DIR__, 3)."/app/Http/Controllers/{$directory}/*.php") as $file) {
            $source = file_get_contents($file);

            preg_match_all("/\\bview\\(\\s*'([\\w.\\-]+)'/", $source, $direct);
            preg_match_all("/themed\\(\\s*'[^']+'\\s*,\\s*'([\\w.\\-]+)'/", $source, $themed);
            preg_match_all("/=>\\s*view\\(\\s*'([\\w.\\-]+)'/", $source, $arms);

            $names = [...$names, ...$direct[1], ...$themed[1], ...$arms[1]];
        }
    }

    $deprecated = ['flights.simbrief_username', 'flights.simbrief_aircraft', 'flights.simbrief_form', 'flights.simbrief_briefing', 'profile.edit', 'profile.connections'];

    return array_values(array_diff(array_unique($names), $deprecated));
}

it('finds the controller view names', function (): void {
    expect(airsideControllerViewNames())
        ->toContain('dashboard.index', 'flights.show', 'pireps.fares', 'airports.show', 'auth.register', 'users.index', 'credits', 'home', 'oauth.authorize', 'ofp.briefing')
        ->not->toContain('profile.edit', 'flights.simbrief_form');
});

it('has an airside view for every controller view name', function (string $name): void {
    expect(View::exists($name))->toBeTrue("airside has no view named [{$name}]");
    expect(View::getFinder()->find($name))->toContain('modules/phpvms-airside-theme/resources/views');
})->with(fn (): array => array_combine(airsideControllerViewNames(), array_map(fn (string $name): array => [$name], airsideControllerViewNames())));

it('has an airside view for the login, verify, registered and terms pages', function (string $name): void {
    expect(View::exists($name))->toBeTrue();
})->with(['auth.login', 'auth.verify', 'auth.registered', 'auth.toc']);

it('renders the old fares partial for an aircraft', function (): void {
    $aircraft = Aircraft::factory()->create(['subfleet_id' => Subfleet::factory()->create()->id]);
    $this->actingAs(User::factory()->create());

    $this->get('/pireps/fares?aircraft_id='.$aircraft->id)->assertOk();
});
