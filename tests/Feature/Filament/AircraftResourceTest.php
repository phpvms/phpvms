<?php

declare(strict_types=1);

use App\Filament\Resources\Subfleets\Resources\Aircraft\Pages\EditAircraft;
use App\Models\Aircraft;
use App\Models\SimBriefAirframe;
use Database\Seeders\RolesPermissionsSeeder;

it('renders the icao airframe dropdown with the stored value resolved from the airframes table', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();
    $aircraft = Aircraft::factory()->create(['icao' => 'B738']);

    SimBriefAirframe::create([
        'icao'        => 'B738',
        'name'        => 'iniBuilds (MSFS) - 737-800',
        'airframe_id' => '3_1677576294832',
    ]);

    // Nested resource under Subfleets; the real route provides the parent.
    $url = EditAircraft::getUrl([
        'record'   => $aircraft->id,
        'subfleet' => $aircraft->subfleet_id,
    ]);

    // getOptionLabelUsing resolves the stored icao against the airframes table,
    // so the page renders the airframe name alongside the code.
    $this->actingAs($admin)
        ->get($url)
        ->assertOk()
        ->assertSee('B738 - iniBuilds (MSFS) - 737-800');
});
