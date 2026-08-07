<?php

use App\Enums\PirepState;
use App\Filament\Resources\Pireps\Pages\ListPireps;
use App\Models\Pirep;
use Database\Seeders\RolesPermissionsSeeder;
use Livewire\Livewire;

/**
 * Smoke test: the PIREP admin list renders as a standard Filament table.
 */
test('admin pirep list renders submitted pireps in the table', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();
    $pirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    $this->actingAs($admin);

    Livewire::test(ListPireps::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$pirep])
        ->assertSee($pirep->ident);
});

test('admin pirep list shows the head metrics subheading', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();
    Pirep::factory()->create(['state' => PirepState::PENDING]);

    $this->actingAs($admin);

    Livewire::test(ListPireps::class)
        ->assertSuccessful()
        ->assertSee(__('pireps.state.pending'));
});
