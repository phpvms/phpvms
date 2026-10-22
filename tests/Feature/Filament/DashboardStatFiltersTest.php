<?php

use App\Enums\PirepState;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\BlockHoursStatWidget;
use App\Filament\Widgets\DistanceStatWidget;
use App\Filament\Widgets\PilotsFlyingStatWidget;
use App\Filament\Widgets\PirepStateChart;
use App\Filament\Widgets\ReportsFiledStatWidget;
use App\Http\Middleware\UpdatePending;
use App\Models\Airline;
use App\Models\Pirep;
use App\Models\PirepPosition;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;

use function Pest\Livewire\livewire;

/**
 * The stat strip used to hardcode a 7-day window and the dashboard supplied no
 * filters at all, so the cards could neither be changed nor explain themselves.
 */
test('stat widgets cover 30 days when the page supplies no filters', function (): void {
    $inside = Pirep::factory()->create([
        'state'        => PirepState::ACCEPTED,
        'submitted_at' => now()->subDays(20),
        'flight_time'  => 120,
        'distance'     => 500,
    ]);

    // Would have fallen inside the old start-of-year default, and still falls
    // outside 30 days.
    Pirep::factory()->create([
        'state'        => PirepState::ACCEPTED,
        'submitted_at' => now()->subDays(45),
        'flight_time'  => 999,
        'distance'     => 9999,
    ]);

    livewire(ReportsFiledStatWidget::class)->assertSee('1');

    livewire(BlockHoursStatWidget::class)
        ->assertSee('2.0')
        ->assertSee('1 legs · 2.0 h average');

    livewire(DistanceStatWidget::class)
        ->assertSee('500')
        ->assertSee('500 nm average leg');

    expect($inside->refresh()->submitted_at)->not->toBeNull();
});

test('stat widgets honour an explicit date filter from the page', function (): void {
    Pirep::factory()->create([
        'state'        => PirepState::ACCEPTED,
        'submitted_at' => now()->subDays(45),
        'flight_time'  => 60,
        'distance'     => 100,
    ]);

    livewire(ReportsFiledStatWidget::class, [
        'pageFilters' => [
            'start_date' => now()->subDays(60)->toDateString(),
            'end_date'   => now()->toDateString(),
            'airlines'   => [],
        ],
    ])->assertSee('1');
});

test('stat widgets honour the airline filter', function (): void {
    $wanted = Airline::factory()->create();
    $other = Airline::factory()->create();

    Pirep::factory()->create([
        'airline_id'   => $wanted->id,
        'state'        => PirepState::ACCEPTED,
        'submitted_at' => now()->subDay(),
    ]);
    Pirep::factory()->create([
        'airline_id'   => $other->id,
        'state'        => PirepState::ACCEPTED,
        'submitted_at' => now()->subDay(),
    ]);

    livewire(ReportsFiledStatWidget::class, [
        'pageFilters' => ['start_date' => null, 'end_date' => null, 'airlines' => [$wanted->id]],
    ])->assertSee('1 accepted · 0 pending');
});

/**
 * A report filed earlier today has to land inside a range that ends today,
 * which is why the end bound is taken at end-of-day rather than `now()`.
 */
test('a report filed earlier today falls inside a range ending today', function (): void {
    Pirep::factory()->create([
        'state'        => PirepState::ACCEPTED,
        'submitted_at' => now()->startOfDay()->addHours(2),
    ]);

    livewire(ReportsFiledStatWidget::class, [
        'pageFilters' => [
            'start_date' => now()->toDateString(),
            'end_date'   => now()->toDateString(),
            'airlines'   => [],
        ],
    ])->assertSee('1 accepted · 0 pending');
});

/**
 * The card counts pilots in the air, not pilots who filed something recently.
 * It previously read off the filed-reports query, which excludes IN_PROGRESS,
 * so a pilot actually flying could never be counted.
 */
test('pilots flying counts live map membership, not filed reports', function (): void {
    $flying = Pirep::factory()->create(['state' => PirepState::IN_PROGRESS, 'submitted_at' => null]);
    PirepPosition::factory()->create(['pirep_id' => $flying->id, 'user_id' => $flying->user_id]);

    // Filed a report today but is not airborne.
    Pirep::factory()->create(['state' => PirepState::ACCEPTED, 'submitted_at' => now()]);

    livewire(PilotsFlyingStatWidget::class)
        ->assertSee('1 flight on the map');
});

test('pilots flying ignores the date filter but respects the airline filter', function (): void {
    $wanted = Airline::factory()->create();
    $other = Airline::factory()->create();

    $mine = Pirep::factory()->create([
        'airline_id' => $wanted->id,
        'state'      => PirepState::IN_PROGRESS,
    ]);
    PirepPosition::factory()->create(['pirep_id' => $mine->id, 'user_id' => $mine->user_id]);

    $theirs = Pirep::factory()->create([
        'airline_id' => $other->id,
        'state'      => PirepState::IN_PROGRESS,
    ]);
    PirepPosition::factory()->create(['pirep_id' => $theirs->id, 'user_id' => $theirs->user_id]);

    // A date window that excludes everything must not zero out a live count.
    livewire(PilotsFlyingStatWidget::class, [
        'pageFilters' => [
            'start_date' => now()->subDays(400)->toDateString(),
            'end_date'   => now()->subDays(300)->toDateString(),
            'airlines'   => [$wanted->id],
        ],
    ])->assertSee('1 flight on the map');
});

/**
 * `user_id` exists on both `pireps` and `pirep_positions`, and `onLiveMap()`
 * joins them, so an unqualified count is ambiguous and errors outright.
 */
test('pilots flying counts distinct pilots across several live flights', function (): void {
    $pilot = User::factory()->create();

    foreach (range(1, 2) as $ignored) {
        $pirep = Pirep::factory()->create([
            'user_id' => $pilot->id,
            'state'   => PirepState::IN_PROGRESS,
        ]);
        PirepPosition::factory()->create(['pirep_id' => $pirep->id, 'user_id' => $pilot->id]);
    }

    livewire(PilotsFlyingStatWidget::class)
        ->assertSee('2 flights on the map');

    // One pilot, two flights: the card's headline number is pilots, not legs.
    expect(Pirep::onLiveMap()->distinct()->count('pireps.user_id'))->toBe(1)
        ->and(Pirep::onLiveMap()->count())->toBe(2);
});

test('the dashboard header renders the period picker beside the layout actions', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);

    $this->actingAs(createAdminUser());

    $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertSeeHtml('data-dashboard-period-trigger')
        // The trigger sits inside the same actions row as Edit layout.
        ->assertSeeHtml('fi-header-actions-ctn fi-report-filters')
        ->assertSeeInOrder([
            'data-dashboard-period-trigger',
            __('filament.dashboard.edit_layout'),
        ])
        ->assertSee(__('filament.reports_period_1d'))
        ->assertSee(__('filament.reports_period_7d'))
        ->assertSee(__('filament.reports_period_14d'))
        ->assertSee(__('filament.reports_period_30d'))
        ->assertSee(__('filament.reports_absolute_range'))
        // Default period, shown on the closed trigger.
        ->assertSee(__('filament.reports_period_30d'));
});

test('choosing a quick range announces the resolved range to the widgets', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);

    $this->actingAs(createAdminUser());

    // The grid sits in a wire:ignore element, so widgets are told over an event
    // rather than through a parent re-render.
    livewire(Dashboard::class)
        ->call('setPeriod', '1d')
        ->assertSet('period', '1d')
        ->assertDispatched('dashboard-period-changed', filters: [
            'start_date' => now()->toDateString(),
            'end_date'   => now()->toDateString(),
            'airlines'   => [],
        ]);
});

test('an absolute range wins over the quick ranges', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);

    $this->actingAs(createAdminUser());

    livewire(Dashboard::class)
        ->set('start', now()->subDays(3)->toDateString())
        ->set('end', now()->subDay()->toDateString())
        ->call('applyCustomRange')
        ->assertSet('period', Dashboard::PERIOD_CUSTOM)
        ->assertDispatched('dashboard-period-changed', filters: [
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date'   => now()->subDay()->toDateString(),
            'airlines'   => [],
        ]);
});

test('a widget re-scopes itself when the dashboard announces a new period', function (): void {
    Pirep::factory()->create(['state' => PirepState::ACCEPTED, 'submitted_at' => now()->subDays(20)]);
    Pirep::factory()->create(['state' => PirepState::ACCEPTED, 'submitted_at' => now()]);

    livewire(ReportsFiledStatWidget::class)
        ->assertSee('2 accepted · 0 pending')
        ->dispatch('dashboard-period-changed', filters: [
            'start_date' => now()->toDateString(),
            'end_date'   => now()->toDateString(),
            'airlines'   => [],
        ])
        ->assertSee('1 accepted · 0 pending');
});

test('the period selection survives a return visit', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);

    $this->actingAs(createAdminUser());

    livewire(Dashboard::class)->call('setPeriod', '7d');

    livewire(Dashboard::class)->assertSet('period', '7d');
});

/**
 * Scoping this chart on `submitted_at` made in-progress, draft and cancelled
 * structurally impossible to plot: none of them carries a filing time, so every
 * period excluded them and three slices were permanently zero.
 */
test('the state chart plots states that never reach filing', function (): void {
    Pirep::factory()->create([
        'state'          => PirepState::IN_PROGRESS,
        'submitted_at'   => null,
        'block_off_time' => now()->subHour(),
    ]);
    Pirep::factory()->create([
        'state'          => PirepState::ACCEPTED,
        'submitted_at'   => now()->subDay(),
        'block_off_time' => now()->subDay(),
    ]);

    livewire(PirepStateChart::class)
        ->assertSeeHtml('data-dashboard-chart="doughnut"')
        // labels are positional, so the counts line up state-for-state
        ->assertSeeHtml('&quot;values&quot;:[1,0,1,0,0,0,0]');
});
