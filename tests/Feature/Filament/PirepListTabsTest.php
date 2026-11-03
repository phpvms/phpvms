<?php

declare(strict_types=1);

use App\Enums\PirepState;
use App\Filament\Resources\Pireps\Pages\ListPireps;
use App\Models\Pirep;
use Database\Seeders\RolesPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
});

it('scopes the list to the active state tab', function (): void {
    $pending = Pirep::factory()->create(['state' => PirepState::PENDING]);
    $accepted = Pirep::factory()->create(['state' => PirepState::ACCEPTED]);
    $rejected = Pirep::factory()->create(['state' => PirepState::REJECTED]);

    Livewire::test(ListPireps::class)
        ->set('activeTab', 'needs_review')
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$accepted, $rejected])
        ->set('activeTab', 'accepted')
        ->assertCanSeeTableRecords([$accepted])
        ->assertCanNotSeeTableRecords([$pending, $rejected])
        ->set('activeTab', 'all')
        ->assertCanSeeTableRecords([$pending, $accepted, $rejected]);
});

it('clears a stale state filter when the tab changes', function (): void {
    $pending = Pirep::factory()->create(['state' => PirepState::PENDING]);
    $accepted = Pirep::factory()->create(['state' => PirepState::ACCEPTED]);

    Livewire::test(ListPireps::class)
        ->set('tableFilters.state.value', PirepState::REJECTED->value)
        ->set('activeTab', 'accepted')
        ->assertSet('tableFilters.state.value', null)
        ->assertCanSeeTableRecords([$accepted])
        ->assertCanNotSeeTableRecords([$pending]);
});
