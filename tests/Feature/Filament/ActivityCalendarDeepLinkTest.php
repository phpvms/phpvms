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

/**
 * A box on the activity calendar deep-links to this list. The two have to
 * agree on what "activity" means, or clicking a box with a count in it lands
 * on an empty page — which is what happened while the calendar counted
 * `acars` rows and the filter queried `pireps.block_off_time`.
 */
it('selects the flights a calendar box counted, including those with no block-off time', function (): void {
    $withBlockOff = Pirep::factory()->create([
        'state'          => PirepState::ACCEPTED,
        'block_off_time' => '2026-08-12 00:30:00',
    ]);

    // No ACARS, so no block_off_time — falls back to created_at, and must
    // still be reachable from the box that counted it.
    $withoutBlockOff = Pirep::factory()->create([
        'state'          => PirepState::ACCEPTED,
        'block_off_time' => null,
        'created_at'     => '2026-08-12 00:45:00',
    ]);

    $outside = Pirep::factory()->create([
        'state'          => PirepState::ACCEPTED,
        'block_off_time' => '2026-08-13 09:00:00',
    ]);

    Livewire::withQueryParams([
        'departed_from' => '2026-08-12 00:00:00',
        'departed_to'   => '2026-08-12 00:59:59',
    ])
        ->test(ListPireps::class)
        ->assertCanSeeTableRecords([$withBlockOff, $withoutBlockOff])
        ->assertCanNotSeeTableRecords([$outside]);
});
