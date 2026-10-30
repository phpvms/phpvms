<?php

declare(strict_types=1);

require_once __DIR__.'/../Support.php';

/**
 * 7.8: the live map page markup the island works on. Polling and selection run
 * in the browser (resources/js/islands/live-map.js), so this checks the page
 * contract: the island root and its config, the header, the templates and the
 * island script.
 */
beforeEach(function (): void {
    $this->pilot = useAirside();
    $this->withoutVite();
});

it('renders the header, the island root and the count badge', function (): void {
    $this->actingAs($this->pilot)->get(route('frontend.livemap.index'))
        ->assertOk()
        ->assertSee('Operations')
        ->assertSee('Live flights')
        ->assertSee('Flights currently in the air, plotted on the globe.')
        ->assertSeeHtml('data-airside-island="live-map"')
        ->assertSeeHtml('data-live-count')
        ->assertSee('0 flights');
});

it('hands the island the poll interval, the initial centre and the map config', function (): void {
    updateSetting('livemap.update_interval', '30');
    updateSetting('livemap.center_coords', '12.5,-45.25');

    $html = $this->actingAs($this->pilot)->get(route('frontend.livemap.index'))->assertOk()->getContent();

    preg_match('/data-airside-island="live-map".*?<script type="application\/json">(.*?)<\/script>/s', $html, $matches);
    $config = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);

    expect($config['updateIntervalSeconds'])->toBe(30)
        ->and($config['initialCenter'])->toBe(['lat' => 12.5, 'lon' => -45.25])
        ->and($config['map'])->toHaveKeys(['basemapLight', 'basemapDark', 'customStyleUrl', 'layers']);
});

it('ships the empty, error and overlay markup without stats or a close button', function (): void {
    $html = $this->actingAs($this->pilot)->get(route('frontend.livemap.index'))->assertOk()->getContent();

    expect($html)
        ->toContain('No flights are live right now')
        ->toContain('Flights appear here as pilots depart.')
        ->toContain('Live flights could not be loaded. Retrying automatically.')
        ->toContain('Flight details could not be loaded.')
        ->toContain('data-live-overlay')
        ->not->toContain('aria-label="Close"');
});

it('loads the live map island script', function (): void {
    $this->actingAs($this->pilot)->get(route('frontend.livemap.index'))
        ->assertOk()
        ->assertSee('live-map', false);
});

it('keeps the island polling floor and the map endpoints in the island script', function (): void {
    $island = file_get_contents(base_path('modules/phpvms-airside-theme/resources/js/islands/live-map.js'));

    expect($island)
        ->toContain('/api/map/live')
        ->toContain('/api/map/pirep/')
        ->toContain('MIN_INTERVAL_SECONDS = 5')
        ->toContain('data-airside-island="live-map"')
        ->toContain('livewire:navigated')
        ->toContain('livewire:navigating');
});
