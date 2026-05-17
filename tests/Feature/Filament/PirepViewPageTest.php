<?php

use App\Enums\PirepState;
use App\Filament\Resources\Pireps\PirepResource;
use App\Models\Pirep;
use Database\Seeders\ShieldSeeder;

test('admin view-pirep page renders detail layout', function (): void {
    $this->seed(ShieldSeeder::class);

    $admin = createAdminUser();
    $pirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    $this->actingAs($admin)
        ->get(PirepResource::getUrl('view', ['record' => $pirep]))
        ->assertSuccessful()
        ->assertSee('fi-pirep-detail', false)
        ->assertSee($pirep->dpt_airport_id)
        ->assertSee($pirep->arr_airport_id)
        ->assertSee($pirep->ident);
});

test('admin pirep list links each card to view-pirep page', function (): void {
    $this->seed(ShieldSeeder::class);

    $admin = createAdminUser();
    $pirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    $expectedUrl = PirepResource::getUrl('view', ['record' => $pirep]);

    $this->actingAs($admin)
        ->get(PirepResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee($expectedUrl, false);
});
