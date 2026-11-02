<?php

use App\Enums\PirepState;
use App\Filament\Resources\Pireps\Actions\PeekAction;
use App\Filament\Resources\Pireps\Pages\ListPireps;
use App\Models\Pirep;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

function peek(Pirep $pirep): TestAction
{
    return TestAction::make(PeekAction::NAME)->table($pirep);
}

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
});

test('the panel accept action accepts a pending pirep', function (): void {
    $pirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    Livewire::test(ListPireps::class)
        ->callAction([peek($pirep), TestAction::make('accept')->table($pirep)]);

    expect($pirep->refresh()->state)->toBe(PirepState::ACCEPTED);
});

test('the panel reject action rejects a pending pirep', function (): void {
    $pirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    Livewire::test(ListPireps::class)
        ->callAction([peek($pirep), TestAction::make('reject')->table($pirep)]);

    expect($pirep->refresh()->state)->toBe(PirepState::REJECTED);
});

test('accept and reject are unavailable once the pirep is decided', function (): void {
    $accepted = Pirep::factory()->create(['state' => PirepState::ACCEPTED]);

    Livewire::test(ListPireps::class)
        ->set('activeTab', 'all')
        ->assertActionHidden([peek($accepted), TestAction::make('accept')->table($accepted)])
        ->assertActionHidden([peek($accepted), TestAction::make('reject')->table($accepted)]);

    expect($accepted->refresh()->state)->toBe(PirepState::ACCEPTED);
});
