<?php

use App\Enums\AcarsType;
use App\Enums\PirepState;
use App\Filament\Resources\Pireps\PirepResource;
use App\Models\Acars;
use App\Models\Pirep;
use App\Models\PirepEvent;
use Database\Seeders\RolesPermissionsSeeder;

test('admin view-pirep flight log renders entries sourced from pirep_events', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();
    $pirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    PirepEvent::factory()->create([
        'pirep_id' => $pirep->id,
        'log'      => 'Flaps set to 15',
    ]);

    // An acars LOG-shaped row for the same pirep must NOT leak into the
    // Flight Log panel now that it reads pirep_events instead.
    Acars::factory()->create([
        'pirep_id' => $pirep->id,
        'type'     => AcarsType::LOG,
        'log'      => 'Should not appear in flight log',
    ]);

    $this->actingAs($admin)
        ->get(PirepResource::getUrl('view', ['record' => $pirep]))
        ->assertSuccessful()
        ->assertSee('Flaps set to 15')
        ->assertDontSee('Should not appear in flight log');
});
