<?php

declare(strict_types=1);

use App\Enums\PirepState;
use App\Filament\Resources\Pireps\Pages\ListPireps;
use App\Filament\Widgets\ActivityCalendarWidget;
use App\Models\Pirep;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Support\Carbon;
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

/**
 * A flight still in the air is activity: it flew, it just has not been filed.
 * It has no `submitted_at` at all, which is why the calendar and this list both
 * scope on `Pirep::ACTIVITY_AT` rather than filing time.
 */
it('counts an in-progress flight and lets the list it links to show it', function (): void {
    // The calendar buckets by calendar day, so within two hours of midnight
    // `now()->subHours(2)` lands in yesterday's row and today's is empty. Pin
    // the clock to midday so the flight and the assertion share a day.
    Carbon::setTestNow(today()->addHours(12));

    $flying = Pirep::factory()->create([
        'state'          => PirepState::IN_PROGRESS,
        'submitted_at'   => null,
        'block_off_time' => now()->subHours(2),
    ]);

    $drafted = Pirep::factory()->create([
        'state'          => PirepState::DRAFT,
        'submitted_at'   => null,
        'block_off_time' => now()->subHours(2),
    ]);

    $cancelled = Pirep::factory()->create([
        'state'          => PirepState::CANCELLED,
        'submitted_at'   => null,
        'block_off_time' => now()->subHours(2),
    ]);

    $widget = new ActivityCalendarWidget();
    $data = json_decode(new ReflectionMethod($widget, 'getViewData')->invoke($widget)['json'], true);

    $today = collect($data['days'])->firstWhere('date', now()->toDateString());

    // The in-progress flight, and only it, lands in its block-off hour.
    expect($today['values'][now()->subHours(2)->hour])->toBe(1)
        ->and($data['max'])->toBe(1);

    Livewire::withQueryParams([
        'departed_from' => now()->subHours(2)->startOfHour()->toDateTimeString(),
        'departed_to'   => now()->subHours(2)->endOfHour()->toDateTimeString(),
    ])
        ->test(ListPireps::class)
        ->assertCanSeeTableRecords([$flying])
        ->assertCanNotSeeTableRecords([$drafted, $cancelled]);

    Carbon::setTestNow();
});
