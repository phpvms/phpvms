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

it('renders the island root', function (): void {
    $this->actingAs($this->pilot)->get(route('frontend.livemap.index'))
        ->assertOk()
        ->assertSeeHtml('data-airside-island="live-map"');
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
