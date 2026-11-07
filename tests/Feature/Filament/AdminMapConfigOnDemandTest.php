<?php

declare(strict_types=1);

use App\Models\MapLayer;
use Illuminate\Support\Facades\DB;

it('does not query map layers on an admin page that draws no map', function (): void {
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->withoutVite()->get('/admin/login')->assertOk();

    expect(collect($queries)->filter(fn (string $sql): bool => str_contains($sql, 'map_layers')))
        ->toBeEmpty();
});

it('hands the live map view the active basemap config', function (): void {
    MapLayer::factory()->basemap()->create(['url_template' => 'https://live-basemap-marker.test/light.json']);

    $html = view('components.admin.live-map', ['pollIntervalMs' => 30000])->render();

    expect($html)->toContain('live-basemap-marker.test');
});
