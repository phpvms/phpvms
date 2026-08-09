<?php

declare(strict_types=1);

use App\Enums\PirepState;
use App\Filament\Pages\Reports\AircraftReport;
use App\Filament\Pages\Reports\FlightsReport;
use App\Filament\Pages\Reports\PilotsReport;
use App\Filament\Widgets\FleetUtilizationTable;
use App\Filament\Widgets\PirepHistoryTable;
use App\Http\Middleware\UpdatePending;
use App\Models\Pirep;
use App\Models\Role;
use App\Models\Subfleet;
use App\Models\User;

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

it('redirects the reports hub to the flights sub-page', function (): void {
    get('/admin/reports')->assertRedirect('/admin/reports/flights');
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

it('shares one filter session key across every report sub-page', function (): void {
    session()->put('reports_filters', [
        'start_date' => '2026-01-01',
        'end_date'   => '2026-06-30',
        'airline_id' => null,
    ]);

    expect(app(FlightsReport::class)->getFiltersSessionKey())->toBe('reports_filters');
    expect(app(PilotsReport::class)->getFiltersSessionKey())->toBe('reports_filters');
    expect(app(AircraftReport::class)->getFiltersSessionKey())->toBe('reports_filters');

    // The period selection set on one sub-page is restored on the others.
    // (The DatePicker normalizes the stored value to a datetime string in
    // the app timezone, so compare the date part.)
    foreach ([FlightsReport::class, PilotsReport::class, AircraftReport::class] as $page) {
        $component = livewire($page);
        $component->assertSet('filters.start_date', fn (mixed $value): bool => str_starts_with((string) $value, '2026-01-01'));
    }
});
