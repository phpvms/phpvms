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
use App\Models\Permission;
use App\Models\Pirep;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use MDDev\DynamicDashboard\Models\Dashboard as DashboardModel;
use MDDev\DynamicDashboard\Models\DashboardWidget;

use function Pest\Livewire\livewire;

/** Mirrors FleetAirframeOptionsTest's selectOptions() helper, for the Radio field. */
function radioOptions(Schema $schema, string $name): array
{
    /** @var Radio $radio */
    $radio = $schema->getComponent(
        fn (Component $component): bool => $component instanceof Radio && $component->getName() === $name,
    );

    return $radio->getOptions();
}

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
        ->assertSeeHtml('class="dashboard-widget-remove"')
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

test('deleteDashboardWidget removes the widget row and leaves the rest untouched', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);

    $admin = createAdminUser();
    $this->actingAs($admin);

    $component = livewire(Dashboard::class);
    $dashboard = DashboardModel::query()->where('created_by', $admin->id)->sole();
    $widget = $dashboard->widgets()->where('type', HoursFlownChart::class)->sole();

    $component->call('deleteDashboardWidget', $widget->id);

    expect(DashboardWidget::query()->find($widget->id))->toBeNull()
        ->and($dashboard->widgets()->count())->toBe(12)
        ->and($dashboard->widgets()->pluck('type')->all())->not->toContain(HoursFlownChart::class);
});

test('deleteDashboardWidget does not delete a widget belonging to another users dashboard', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);

    $admin = createAdminUser();
    $other = createAdminUser();

    $this->actingAs($other);
    livewire(Dashboard::class);
    $otherDashboard = DashboardModel::query()->where('created_by', $other->id)->sole();
    $otherWidget = $otherDashboard->widgets()->firstOrFail();

    $this->actingAs($admin);
    $component = livewire(Dashboard::class);

    $component->call('deleteDashboardWidget', $otherWidget->id);

    expect(DashboardWidget::query()->find($otherWidget->id))->not->toBeNull()
        ->and($otherDashboard->widgets()->count())->toBe(13);
});

test('deleteDashboardWidget ignores a client-set currentDashboardId', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);

    $admin = createAdminUser();
    $other = createAdminUser();

    $this->actingAs($other);
    livewire(Dashboard::class);
    $otherDashboard = DashboardModel::query()->where('created_by', $other->id)->sole();
    $otherWidget = $otherDashboard->widgets()->firstOrFail();

    // `currentDashboardId` is `#[Session]`, not `#[Locked]`, so the client can
    // set it to any id. The delete must not trust it.
    $this->actingAs($admin);
    livewire(Dashboard::class)
        ->set('currentDashboardId', $otherDashboard->id)
        ->call('deleteDashboardWidget', $otherWidget->id);

    expect(DashboardWidget::query()->find($otherWidget->id))->not->toBeNull()
        ->and($otherDashboard->widgets()->count())->toBe(13);
});

test('deleteDashboardWidget refuses to touch a locked dashboard', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);

    $admin = createAdminUser();
    $this->actingAs($admin);

    $component = livewire(Dashboard::class);
    $dashboard = DashboardModel::query()->where('created_by', $admin->id)->sole();
    $widget = $dashboard->widgets()->where('type', HoursFlownChart::class)->sole();

    $dashboard->update(['is_locked' => true]);

    $component->call('deleteDashboardWidget', $widget->id)->assertForbidden();

    expect(DashboardWidget::query()->find($widget->id))->not->toBeNull();
});

test('deleteDashboardWidget and addDashboardWidget are forbidden without edit rights', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);
    Filament::setCurrentPanel('admin');

    $viewer = User::factory()->create();
    Permission::firstOrCreate(['name' => 'view:dashboard', 'guard_name' => 'web']);
    $viewer->givePermissionTo('view:dashboard');

    $this->actingAs($viewer->fresh());

    expect(Dashboard::canAccess())->toBeTrue()
        ->and(Dashboard::canEdit())->toBeFalse();

    $component = livewire(Dashboard::class)->assertDontSeeHtml('dashboard-widget-remove');
    $dashboard = DashboardModel::query()->where('created_by', $viewer->id)->sole();
    $widget = $dashboard->widgets()->where('type', HoursFlownChart::class)->sole();

    $component->call('deleteDashboardWidget', $widget->id)->assertForbidden();

    expect(DashboardWidget::query()->find($widget->id))->not->toBeNull()
        ->and($dashboard->widgets()->count())->toBe(13);

    // The add action is gated a step earlier, by ->visible(static::canEdit()),
    // so a view-only user never gets to mount it at all.
    $dashboard->widgets()->where('type', LandingRateChart::class)->sole()->delete();

    livewire(Dashboard::class)->assertActionHidden('addDashboardWidget');

    expect($dashboard->widgets()->where('type', LandingRateChart::class)->exists())->toBeFalse();
});

test('addDashboardWidget refuses to touch a locked dashboard', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);
    Filament::setCurrentPanel('admin');

    $admin = createAdminUser();
    $this->actingAs($admin);

    livewire(Dashboard::class);
    $dashboard = DashboardModel::query()->where('created_by', $admin->id)->sole();
    $dashboard->widgets()->where('type', LandingRateChart::class)->sole()->delete();
    $dashboard->update(['is_locked' => true]);

    livewire(Dashboard::class)
        ->callAction('addDashboardWidget', ['type' => LandingRateChart::class])
        ->assertForbidden();

    expect($dashboard->widgets()->where('type', LandingRateChart::class)->exists())->toBeFalse();
});

test('deleteDashboardWidget is a no-op for a non-existent id', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);

    $admin = createAdminUser();
    $this->actingAs($admin);

    $component = livewire(Dashboard::class);
    $dashboard = DashboardModel::query()->where('created_by', $admin->id)->sole();
    $nonExistentId = (DashboardWidget::query()->max('id') ?? 0) + 1000;

    $component->call('deleteDashboardWidget', $nonExistentId)->assertHasNoErrors();

    expect($dashboard->widgets()->count())->toBe(13);
});

test('addDashboardWidget creates a row using the widget classes own default size', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);

    $admin = createAdminUser();
    $this->actingAs($admin);
    // getAvailableWidgetOptions() discovers widgets off the current Filament
    // panel; a component-level Livewire test never runs through the
    // SetUpPanel middleware that a real HTTP request would, so it has to be
    // set explicitly.
    Filament::setCurrentPanel('admin');

    $component = livewire(Dashboard::class);
    $dashboard = DashboardModel::query()->where('created_by', $admin->id)->sole();
    $dashboard->widgets()->where('type', LandingRateChart::class)->sole()->delete();

    $maxY = $dashboard->widgets()->max('y');

    $component->callAction('addDashboardWidget', ['type' => LandingRateChart::class])
        ->assertHasNoErrors();

    $created = $dashboard->widgets()->where('type', LandingRateChart::class)->sole();

    expect($created->section_slug)->toBe('main')
        ->and($created->x)->toBe(0)
        ->and($created->y)->toBeGreaterThan($maxY)
        ->and($created->w)->toBe(LandingRateChart::getDynamicDashboardDefaultWidth())
        ->and($created->h)->toBe(LandingRateChart::getDynamicDashboardDefaultHeight());
});

test('addDashboardWidget options exclude widgets already on the dashboard', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);

    $admin = createAdminUser();
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');

    // Mounting the page creates the 13 default widgets, so the picker starts
    // empty: the modal says so and drops its submit button rather than offering
    // an empty choice.
    $full = livewire(Dashboard::class)->mountAction('addDashboardWidget');

    expect($full->instance()->getMountedAction()?->getModalSubmitAction())->toBeNull();

    $dashboard = DashboardModel::query()->where('created_by', $admin->id)->sole();

    $dashboard->widgets()->where('type', LandingRateChart::class)->sole()->delete();

    $page = livewire(Dashboard::class)->mountAction('addDashboardWidget');
    $options = radioOptions(
        $page->instance()->getSchema($page->instance()->getMountedActionSchemaName()),
        'type',
    );

    expect($options)->toHaveCount(1)
        ->and($options)->toHaveKey(LandingRateChart::class);
});
