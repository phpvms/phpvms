<?php

declare(strict_types=1);

use App\Enums\PirepState;
use App\Filament\Pages\Reports\AircraftReport;
use App\Filament\Pages\Reports\FlightsReport;
use App\Filament\Pages\Reports\PilotsReport;
use App\Filament\Widgets\FleetUtilizationTable;
use App\Filament\Widgets\PirepHistoryTable;
use App\Http\Middleware\UpdatePending;
use App\Models\Airline;
use App\Models\Pirep;
use App\Models\Role;
use App\Models\Subfleet;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $role = Role::create(['name' => Role::superAdminName(), 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->withoutMiddleware(UpdatePending::class);
    actingAs($user);
});

it('renders the admin dashboard without error', function (): void {
    get('/admin')->assertOk();
});

it('renders every report page from its own navigation group entry', function (): void {
    get('/admin/reports/flights')->assertOk();
    get('/admin/reports/pilots')->assertOk();
    get('/admin/reports/aircraft')->assertOk();
});

it('puts the period and airline pickers in the report page header', function (): void {
    get('/admin/reports/flights')
        ->assertOk()
        ->assertSee('fi-report-filter-trigger', escape: false)
        // .fi-header is overflow-hidden, which makes floating-ui flip the panel
        // upward into the clipped region. Teleporting it out is what keeps the
        // absolute-range inputs reachable.
        ->assertSee('.teleport', escape: false)
        ->assertSee(__('filament.reports_period_30d'))
        ->assertSee(__('filament.reports_all_airlines'))
        ->assertSee(__('filament.reports_quick_ranges'))
        ->assertSee(__('filament.reports_absolute_range'));
});

it('renders the flights report page under operations', function (): void {
    get('/admin/reports/flights')
        ->assertOk()
        ->assertSee(__('filament.reports_flights'));
});

it('shows pirep history rows on the flights report page', function (): void {
    $pirep = Pirep::factory()->create([
        'state'        => PirepState::ACCEPTED,
        'submitted_at' => now()->toDateTimeString(),
    ]);

    get('/admin/reports/flights')
        ->assertOk()
        ->assertSee(__('filament.reports_flights'));

    livewire(PirepHistoryTable::class)
        ->assertCanSeeTableRecords([$pirep]);
});

it('shows fleet utilization on the aircraft report page', function (): void {
    $subfleet = Subfleet::factory()->hasAircraft(2, [
        'flight_time' => 120,
    ])->create();

    get('/admin/reports/aircraft')
        ->assertOk()
        ->assertSee(__('filament.reports_aircraft'));

    livewire(FleetUtilizationTable::class)
        ->assertCanSeeTableRecords([$subfleet]);
});

it('filters the pireps list from the calendar deep-link', function (): void {
    $pirep = Pirep::factory()->create([
        'block_off_time' => '2026-08-02 01:30:00',
        'state'          => PirepState::ACCEPTED,
    ]);
    $other = Pirep::factory()->create([
        'block_off_time' => '2026-08-03 01:30:00',
        'state'          => PirepState::ACCEPTED,
    ]);

    get('/admin/pireps?departed_from=2026-08-02%2001:00:00&departed_to=2026-08-02%2001:59:59')
        ->assertOk()
        ->assertSee((string) $pirep->flight->flight_number)
        ->assertDontSee((string) $other->flight->flight_number);
});

it('ignores malformed calendar deep-link dates instead of erroring', function (): void {
    get('/admin/pireps?departed_from=not-a-date&departed_to=2026-08-02%2001:59:59')
        ->assertOk();
});

it('shares one filter session key across every report page', function (): void {
    session()->put('reports_filters', [
        'period'   => 'ytd',
        'start'    => null,
        'end'      => null,
        'airlines' => [],
    ]);

    // The period picked on one report page is restored on the others.
    foreach ([FlightsReport::class, PilotsReport::class, AircraftReport::class] as $page) {
        livewire($page)
            ->assertSet('period', 'ytd')
            ->assertSet('filters.start_date', now()->startOfYear()->toDateString());
    }
});

it('resolves each quick range into the dates handed to the widgets', function (): void {
    livewire(FlightsReport::class)
        ->call('setPeriod', '7d')
        ->assertSet('filters.start_date', now()->subDays(6)->toDateString())
        ->assertSet('filters.end_date', now()->toDateString())
        ->call('setPeriod', 'ytd')
        ->assertSet('filters.start_date', now()->startOfYear()->toDateString());
});

it('reads the period and airlines from the query string', function (): void {
    $airline = Airline::factory()->create();

    get('/admin/reports/flights?period=7d&airlines[]='.$airline->id)->assertOk();

    // #[Url] hydration: the query string alone must drive the resolved range
    // and airline set, with no mount parameters involved.
    Livewire::withQueryParams(['period' => '7d', 'airlines' => [(string) $airline->id]])
        ->test(FlightsReport::class)
        ->assertSet('period', '7d')
        ->assertSet('filters.start_date', now()->subDays(6)->toDateString())
        ->assertSet('filters.airlines', [(string) $airline->id]);
});

it('applies a custom absolute range over the quick ranges', function (): void {
    livewire(FlightsReport::class)
        ->set('start', '2026-01-01')
        ->set('end', '2026-06-30')
        ->call('applyCustomRange')
        ->assertSet('period', 'custom')
        ->assertSet('filters.start_date', '2026-01-01')
        ->assertSet('filters.end_date', '2026-06-30');
});

it('limits report widgets to the selected airlines', function (): void {
    $wanted = Airline::factory()->create();
    $other = Airline::factory()->create();

    $mine = Pirep::factory()->create([
        'airline_id'   => $wanted->id,
        'state'        => PirepState::ACCEPTED,
        'submitted_at' => now()->toDateTimeString(),
    ]);
    $theirs = Pirep::factory()->create([
        'airline_id'   => $other->id,
        'state'        => PirepState::ACCEPTED,
        'submitted_at' => now()->toDateTimeString(),
    ]);

    livewire(PirepHistoryTable::class, [
        'pageFilters' => [
            'start_date' => now()->subDays(29)->toDateString(),
            'end_date'   => now()->toDateString(),
            'airlines'   => [$wanted->id],
        ],
    ])
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('shows every airline when none are selected', function (): void {
    $pireps = Pirep::factory()->count(2)->create([
        'state'        => PirepState::ACCEPTED,
        'submitted_at' => now()->toDateTimeString(),
    ]);

    livewire(PirepHistoryTable::class, [
        'pageFilters' => [
            'start_date' => now()->subDays(29)->toDateString(),
            'end_date'   => now()->toDateString(),
            'airlines'   => [],
        ],
    ])
        ->assertCanSeeTableRecords($pireps);
});
