<?php

declare(strict_types=1);

use App\Models\Airport;
use App\Models\User;
use Igaster\LaravelTheme\Facades\Theme;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Theme::set('skylight');
    updateSetting('general.theme', 'skylight');
});

it('passes the poll interval and the configured centre as the initial camera', function (): void {
    updateSetting('livemap.update_interval', 90);
    updateSetting('livemap.center_coords', '51.4700,-0.4543');

    $this->actingAs(User::factory()->create())
        ->get('/livemap')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('LiveMap/Index', false)
            ->where('updateIntervalSeconds', 90)
            ->where('initialCenter.lat', 51.47)
            ->where('initialCenter.lon', -0.4543));
});

it('falls back to the first hub airport when the operator has not configured a centre', function (): void {
    // Simulates "unset" — SettingsSeeder always seeds a row, and
    // SettingService::store() is a no-op on a null value (it never inserts
    // or clears), so an empty string is how a test gets `filled()` to fail
    // the way an absent/blank setting would in production.
    updateSetting('livemap.center_coords', '');

    Airport::factory()->create(['hub' => false, 'lat' => 10.0, 'lon' => 20.0]);
    $hub = Airport::factory()->create(['hub' => true, 'lat' => 40.7128, 'lon' => -74.0060]);

    $this->actingAs(User::factory()->create())
        ->get('/livemap')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('initialCenter.lat', $hub->lat)
            ->where('initialCenter.lon', $hub->lon));
});

it('sends a null centre when neither the setting nor a hub airport resolves', function (): void {
    updateSetting('livemap.center_coords', '');

    $this->actingAs(User::factory()->create())
        ->get('/livemap')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('initialCenter', null));
});

it('ignores a hub airport with no coordinates and falls through to null', function (): void {
    updateSetting('livemap.center_coords', '');
    Airport::factory()->create(['hub' => true, 'lat' => null, 'lon' => null]);

    $this->actingAs(User::factory()->create())
        ->get('/livemap')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('initialCenter', null));
});

it('renders the Blade live map page on a blade theme, unaffected by the centre resolution', function (): void {
    Theme::set('seven');
    updateSetting('general.theme', 'seven');

    $this->actingAs(User::factory()->create())
        ->get('/livemap')
        ->assertOk()
        ->assertViewIs('livemap.index');
});
