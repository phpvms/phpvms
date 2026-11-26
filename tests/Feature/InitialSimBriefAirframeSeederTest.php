<?php

declare(strict_types=1);

use App\Models\SimBriefAircraft;
use App\Models\SimBriefAirframe;
use Database\Seeders\BaseDataSeeder;

test('a fresh install receives the bundled simbrief airframes', function (): void {
    $this->seed(BaseDataSeeder::class);

    expect(SimBriefAirframe::query()->count())->toBeGreaterThan(0);
    expect(SimBriefAircraft::query()->count())->toBeGreaterThan(0);
    $this->assertDatabaseHas('simbrief_airframes', ['icao' => 'B738', 'name' => 'Default']);
    $this->assertDatabaseHas('simbrief_aircraft', ['icao' => 'B738']);
});

test('existing simbrief airframes are left untouched', function (): void {
    SimBriefAirframe::query()->create(['icao' => 'CUST', 'name' => 'Custom airframe']);

    $this->seed(BaseDataSeeder::class);

    expect(SimBriefAirframe::query()->pluck('icao')->all())->toBe(['CUST']);
});
