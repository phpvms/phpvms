<?php

use App\Enums\PirepState;
use App\Filament\Resources\Pireps\PirepResource;
use App\Models\Pirep;
use App\Services\PirepService;
use Database\Seeders\RolesPermissionsSeeder;

test('admin view-pirep page renders detail layout', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();
    $pirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    $this->actingAs($admin)
        ->get(PirepResource::getUrl('view', ['record' => $pirep]))
        ->assertSuccessful()
        ->assertSee('subtabs', false)
        ->assertSee($pirep->dpt_airport_id)
        ->assertSee($pirep->arr_airport_id)
        ->assertSee($pirep->ident);
});

test('admin pirep list links each card to view-pirep page', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();
    $pirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    $expectedUrl = PirepResource::getUrl('view', ['record' => $pirep]);

    $this->actingAs($admin)
        ->get(PirepResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee($expectedUrl, false);
});

test('view-pirep page renders the original flight card from the archive after flight and aircraft are hard-deleted', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();
    $pirep = Pirep::factory()->create(['state' => PirepState::IN_PROGRESS]);
    $pirepSvc = app(PirepService::class);
    $pirepSvc->file($pirep);
    $pirepSvc->submit($pirep);

    $pirep->refresh();

    $flight = $pirep->flight;
    $aircraft = $pirep->aircraft;
    $flight->forceDelete();
    $aircraft->forceDelete();

    $archivedCallsign = $pirep->metadata->flight['callsign'];
    $archivedRegistration = $pirep->metadata->aircraft['registration'];

    $this->actingAs($admin)
        ->get(PirepResource::getUrl('view', ['record' => $pirep]))
        ->assertSuccessful()
        ->assertSee(__('filament.original_flight'))
        ->assertSee($archivedCallsign)
        ->assertSee($archivedRegistration);
});

test('view-pirep page renders without error for a pirep with no archive row', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();
    $pirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    $this->actingAs($admin)
        ->get(PirepResource::getUrl('view', ['record' => $pirep]))
        ->assertSuccessful()
        ->assertSee(__('filament.original_flight_empty'));
});
