<?php

use App\Enums\PirepState;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\AccountWidget;
use App\Filament\Widgets\ActivityCalendarWidget;
use App\Filament\Widgets\BlockHoursStatWidget;
use App\Filament\Widgets\DistanceFlownChart;
use App\Filament\Widgets\DistanceStatWidget;
use App\Filament\Widgets\HoursFlownChart;
use App\Filament\Widgets\LandingRateChart;
use App\Filament\Widgets\PilotsFlyingStatWidget;
use App\Filament\Widgets\PirepStateChart;
use App\Filament\Widgets\RecentActionWidget;
use App\Filament\Widgets\ReportsFiledStatWidget;
use App\Filament\Widgets\TailsAvailableStatWidget;
use App\Filament\Widgets\VersionWidget;
use App\Http\Middleware\UpdatePending;
use App\Models\Pirep;
use Database\Seeders\RolesPermissionsSeeder;
use MDDev\DynamicDashboard\Models\Dashboard as DashboardModel;
use MDDev\DynamicDashboard\Models\DashboardWidget;

use function Pest\Livewire\livewire;

test('admin dashboard renders the welcome heading and the new widgets', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);

    $admin = createAdminUser(['name' => 'Jordan Rivera']);
    $pendingPirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    $this->actingAs($admin);

    // The widgets are lazy-loaded, so their content is checked via livewire().
    $response = $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertSee('Welcome back, Jordan Rivera')
        ->assertSee(__('filament.dashboard.edit_layout'))
        ->assertSee(__('filament.dashboard.save_layout'))
        ->assertSeeHtml('data-dashboard-layout-edit')
        ->assertSeeHtml('data-dashboard-layout-save')
        ->assertSeeHtml('class="dashboard-canvas is-readonly"')
        ->assertSeeHtml('class="grid-stack"')
        ->assertSeeHtml('wire:ignore')
        ->assertDontSeeHtml('x-init="init()"')
        ->assertSeeHtml('x-data="phpvmsDashboardGrid')
        ->assertSeeHtml('gs-w="2"')
        ->assertSeeHtml('gs-w="3"')
        ->assertSeeHtml('gs-cell-height="100"')
        ->assertSeeHtml('gs-min-w="2"')
        ->assertSeeHtml('gs-max-w="3"')
        ->assertSeeHtml('gs-min-h="1"')
        ->assertSeeHtml('gs-max-h="1"');

    $dashboard = DashboardModel::query()->where('created_by', $admin->id)->sole();
    $widgetTypes = $dashboard->widgets()->pluck('type')->all();

    expect($dashboard->is_personal)->toBeTrue()
        ->and($widgetTypes)->toBe([
            ReportsFiledStatWidget::class,
            BlockHoursStatWidget::class,
            DistanceStatWidget::class,
            PilotsFlyingStatWidget::class,
            TailsAvailableStatWidget::class,
            AccountWidget::class,
            VersionWidget::class,
            ActivityCalendarWidget::class,
            RecentActionWidget::class,
            HoursFlownChart::class,
            DistanceFlownChart::class,
            PirepStateChart::class,
            LandingRateChart::class,
        ])
        ->and(ActivityCalendarWidget::getDynamicDashboardMinHeight())->toBe(3)
        ->and(HoursFlownChart::getDynamicDashboardMinHeight())->toBe(3)
        ->and(DistanceFlownChart::getDynamicDashboardMinHeight())->toBe(3)
        ->and(PirepStateChart::getDynamicDashboardMinHeight())->toBe(3)
        ->and(LandingRateChart::getDynamicDashboardMinHeight())->toBe(3);

    $response->assertSeeHtml('gs-id="'.$dashboard->widgets()->firstOrFail()->id.'"');

    livewire(RecentActionWidget::class)
        ->assertSeeHtml('fi-ta')
        ->assertSee($pendingPirep->ident);

    livewire(ActivityCalendarWidget::class)
        ->assertSeeHtml('data-dashboard-chart="calendar"')
        ->assertSee(__('filament.dashboard.activity_calendar'));

    livewire(HoursFlownChart::class)
        ->assertSeeHtml('data-dashboard-chart="bar"')
        ->assertSee(__('filament.dashboard.hours_flown'));

    livewire(ReportsFiledStatWidget::class)
        ->assertSeeHtml('class="dashboard-stat-card"')
        ->assertSee(__('filament.dashboard.reports_filed'));
});

test('admin dashboard saves and resets the current users package layout', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);

    $admin = createAdminUser();
    $this->actingAs($admin);

    $component = livewire(Dashboard::class);
    $dashboard = DashboardModel::query()->where('created_by', $admin->id)->sole();
    $hours = $dashboard->widgets()->where('type', HoursFlownChart::class)->sole();

    $component
        ->call('persistLayout', [[
            'id'      => $hours->id,
            'section' => 'main',
            'x'       => 0,
            'y'       => 8,
            'w'       => 7,
            'h'       => 4,
        ]])
        ->assertHasNoErrors();

    expect($hours->refresh()->only(['x', 'y', 'w', 'h']))->toBe([
        'x' => 0,
        'y' => 8,
        'w' => 7,
        'h' => 4,
    ]);

    $component
        ->call('resetDashboardLayout')
        ->assertDispatched('dashboard-layout:reset')
        ->assertHasNoErrors();

    expect(DashboardWidget::query()->where('dashboard_id', $dashboard->id)->count())->toBe(13)
        ->and(DashboardWidget::query()
            ->where('dashboard_id', $dashboard->id)
            ->where('type', ReportsFiledStatWidget::class)
            ->sole()
            ->only(['x', 'y', 'w', 'h']))->toBe([
                'x' => 0,
                'y' => 0,
                'w' => 2,
                'h' => 1,
            ]);
});

test('admin dashboard upgrades the legacy stats strip without resetting other widgets', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);

    $admin = createAdminUser();
    $this->actingAs($admin);

    livewire(Dashboard::class);

    $dashboard = DashboardModel::query()->where('created_by', $admin->id)->sole();
    $dashboard->widgets()->delete();

    DashboardWidget::query()->create([
        'dashboard_id'  => $dashboard->id,
        'name'          => 'Key operating figures',
        'type'          => 'App\\Filament\\Widgets\\StatsStripWidget',
        'section_slug'  => 'main',
        'x'             => 0,
        'y'             => 4,
        'w'             => 12,
        'h'             => 1,
        'is_active'     => true,
        'display_title' => false,
        'settings'      => [],
    ]);
    $accountWidget = DashboardWidget::query()->create([
        'dashboard_id'  => $dashboard->id,
        'name'          => 'Account',
        'type'          => AccountWidget::class,
        'section_slug'  => 'main',
        'x'             => 6,
        'y'             => 9,
        'w'             => 6,
        'h'             => 2,
        'is_active'     => true,
        'display_title' => false,
        'settings'      => [],
    ]);

    livewire(Dashboard::class)->assertHasNoErrors();

    expect($dashboard->widgets()->where('type', 'App\\Filament\\Widgets\\StatsStripWidget')->exists())->toBeFalse()
        ->and($dashboard->widgets()->whereIn('type', [
            ReportsFiledStatWidget::class,
            BlockHoursStatWidget::class,
            DistanceStatWidget::class,
            PilotsFlyingStatWidget::class,
            TailsAvailableStatWidget::class,
        ])->where('y', 4)->count())->toBe(5)
        ->and($accountWidget->refresh()->only(['x', 'y', 'w', 'h']))->toBe([
            'x' => 6,
            'y' => 9,
            'w' => 6,
            'h' => 2,
        ]);
});
