<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\AuthorizesAccess;
use App\Filament\Widgets\ActivityCalendarWidget;
use App\Filament\Widgets\BlockHoursStatWidget;
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
use App\Models\User;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View as ViewComponent;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Renderless;
use MDDev\DynamicDashboard\Contracts\DynamicWidget;
use MDDev\DynamicDashboard\Models\Dashboard as DashboardModel;
use MDDev\DynamicDashboard\Models\DashboardWidget;
use MDDev\DynamicDashboard\Pages\DynamicDashboard;
use MDDev\DynamicDashboard\Templates\TemplateRegistry;
use Override;

class Dashboard extends DynamicDashboard
{
    use AuthorizesAccess;

    private const string LEGACY_STATS_WIDGET = 'App\\Filament\\Widgets\\StatsStripWidget';

    protected static string|array $routeMiddleware = [UpdatePending::class];

    protected static string|BackedEnum|null $navigationIcon = Phosphor::GaugeLight;

    /**
     * Default placement only. Each widget class owns its own size through
     * `getDynamicDashboardDefaultWidth()`/`Height()`, and GridStack clamps to
     * the matching min/max — repeating the size here just lets the two drift.
     *
     * Rows are 100px (resources/dashboard-templates/flat-12.json), so a
     * widget's on-screen height is its row count × 100.
     *
     * @var list<array{name: string, type: class-string<DynamicWidget>, x: int, y: int}>
     */
    private const array DEFAULT_WIDGETS = [
        ['name' => 'Reports filed', 'type' => ReportsFiledStatWidget::class, 'x' => 0, 'y' => 0],
        ['name' => 'Block hours', 'type' => BlockHoursStatWidget::class, 'x' => 2, 'y' => 0],
        ['name' => 'Distance', 'type' => DistanceStatWidget::class, 'x' => 4, 'y' => 0],
        ['name' => 'Pilots flying', 'type' => PilotsFlyingStatWidget::class, 'x' => 6, 'y' => 0],
        ['name' => 'Tails available', 'type' => TailsAvailableStatWidget::class, 'x' => 9, 'y' => 0],
        ['name' => 'Flight activity by hour', 'type' => ActivityCalendarWidget::class, 'x' => 0, 'y' => 1],
        ['name' => 'phpVMS', 'type' => VersionWidget::class, 'x' => 8, 'y' => 1],
        ['name' => 'Recent action', 'type' => RecentActionWidget::class, 'x' => 8, 'y' => 2],
        ['name' => 'Average landing rate', 'type' => LandingRateChart::class, 'x' => 0, 'y' => 5],
        ['name' => 'Flight hours', 'type' => HoursFlownChart::class, 'x' => 0, 'y' => 8],
        ['name' => 'PIREPs by state', 'type' => PirepStateChart::class, 'x' => 8, 'y' => 8],
    ];

    /**
     * The dashboard is the panel's home page, so it owns `/admin` itself.
     *
     * Filament's own Dashboard page does this; DynamicDashboard extends the
     * plain Page, which would otherwise slug it to `/admin/dashboard` and leave
     * `/admin` as a redirect.
     */
    #[Override]
    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    #[Override]
    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $dashboard = DashboardModel::query()->firstOrCreate(
            [
                'page'        => static::class,
                'is_personal' => true,
                'created_by'  => auth()->id(),
            ],
            [
                'name'         => __('common.dashboard'),
                'description'  => null,
                'is_active'    => true,
                'is_locked'    => false,
                'template_key' => 'flat-12',
            ],
        );

        $this->upgradeLegacyStatsWidget($dashboard);

        if (!$dashboard->widgets()->exists()) {
            $this->createDefaultWidgets($dashboard);
        }

        $this->currentDashboardId = $dashboard->id;

        parent::mount();
    }

    #[Override]
    public function getAvailableDashboards(): Builder
    {
        return DashboardModel::query()
            ->where('page', static::class)
            ->where('is_personal', true)
            ->where('created_by', auth()->id())
            ->where('is_active', true)
            ->orderBy('ordering');
    }

    #[Override]
    public function getWidgetsContentComponent(): Component
    {
        $template = $this->getCurrentDashboard()->template
            ?? app(TemplateRegistry::class)->default();

        return ViewComponent::make('filament-dynamic-dashboard::livewire.dashboard-grid')
            ->viewData(fn (): array => [
                'template'         => $template,
                'widgetsBySection' => $this->buildWidgetsViewData($template),
                'pageFilters'      => $this->filters ?? [],
                'canEdit'          => static::canEdit(),
                'canDrag'          => false,
            ]);
    }

    #[Override]
    public function getHeading(): string|Htmlable
    {
        /** @var User $user */
        $user = auth()->user();

        return __('filament.dashboard.welcome', ['name' => $user->name]);
    }

    #[Override]
    public function getSubheading(): string|Htmlable|null
    {
        /** @var User $user */
        $user = auth()->user();

        $segments = [];

        if (filled($user->country)) {
            $segments[] = ['flag' => asset('/assets/global/flags/4x3/'.Str::lower($user->country).'.svg')];
        }

        if (filled($user->rank?->name)) {
            $segments[] = ['text' => $user->rank->name];
        }

        if (filled($primaryRole = $user->roles->first()?->name)) {
            $segments[] = ['text' => Str::headline($primaryRole)];
        }

        if ($segments === []) {
            return null;
        }

        return view('filament.dashboard.partials.welcome-meta', ['segments' => $segments]);
    }

    #[Renderless]
    public function resetDashboardLayout(): void
    {
        $dashboard = $this->getCurrentDashboard();

        abort_unless($dashboard instanceof DashboardModel && static::canEdit(), 403);

        DB::transaction(function () use ($dashboard): void {
            $dashboard->widgets()->delete();
            $this->createDefaultWidgets($dashboard);
        });

        $this->dispatch('dashboard-layout:reset');
    }

    /**
     * Delete a single widget from the current dashboard.
     *
     * Scoped through `getCurrentDashboard()`, which resolves via
     * `getAvailableDashboards()` and so only ever yields a dashboard owned by
     * the current user. The raw `currentDashboardId` property is *not* usable
     * as a scope: the vendor base class declares it `#[Session]`, not
     * `#[Locked]`, so a client can set it to any id.
     *
     * Silently no-ops if the widget isn't on that dashboard — the client
     * already removed it from the DOM, so a 500 here would just be noise.
     *
     * Marked `#[Renderless]` so the call hits the DB but skips Livewire's
     * re-render; the frontend removes the DOM node itself.
     */
    #[Renderless]
    public function deleteDashboardWidget(int $id): void
    {
        $dashboard = $this->getCurrentDashboard();

        abort_unless(
            $dashboard instanceof DashboardModel && static::canEdit() && !$dashboard->is_locked,
            403,
        );

        $dashboard->widgets()->whereKey($id)->delete();
    }

    /** @return array<Action> */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetDashboardLayout')
                ->label(__('filament.dashboard.reset_layout'))
                ->icon(Phosphor::ArrowsClockwiseLight)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(__('filament.dashboard.reset_layout_heading'))
                ->modalDescription(__('filament.dashboard.reset_layout_description'))
                ->visible(static::canEdit())
                ->extraAttributes([
                    'class'                       => 'hidden',
                    'data-dashboard-layout-reset' => '',
                ])
                ->action(fn () => $this->resetDashboardLayout()),
            Action::make('editDashboardLayout')
                ->label(__('filament.dashboard.edit_layout'))
                ->icon(Phosphor::GridFourLight)
                ->color('gray')
                ->visible(static::canEdit())
                ->alpineClickHandler("window.dispatchEvent(new CustomEvent('dashboard-layout:edit'))")
                ->extraAttributes(['data-dashboard-layout-edit' => '']),
            Action::make('addDashboardWidget')
                ->label(__('filament.dashboard.add_widget'))
                ->icon(Phosphor::PlusLight)
                ->color('gray')
                ->visible(static::canEdit())
                ->extraAttributes([
                    'class'                     => 'hidden',
                    'data-dashboard-layout-add' => '',
                ])
                // The options are resolved when the modal mounts rather than
                // when the page renders, because removing a widget is
                // renderless and would otherwise leave a stale picker behind.
                ->schema(fn (): array => $this->getAddableWidgetOptions() === []
                    ? [Text::make(__('filament.dashboard.add_widget_all_added'))]
                    : [
                        Radio::make('type')
                            ->label(__('filament.dashboard.add_widget_type'))
                            ->options(fn (): array => $this->getAddableWidgetOptions())
                            ->required(),
                    ])
                ->modalSubmitAction(fn (): ?bool => $this->getAddableWidgetOptions() === [] ? false : null)
                ->action(function (array $data): void {
                    $dashboard = $this->getCurrentDashboard();

                    abort_unless(
                        $dashboard instanceof DashboardModel && static::canEdit() && !$dashboard->is_locked,
                        403,
                    );

                    /** @var class-string<DynamicWidget> $type */
                    $type = $data['type'];

                    $maxY = $dashboard->widgets()->where('section_slug', 'main')->max('y') ?? -1;

                    $this->createDashboardWidget($dashboard, [
                        'name' => $type::getWidgetLabel(),
                        'type' => $type,
                        'x'    => 0,
                        'y'    => $maxY + 1,
                    ]);

                    Notification::make()
                        ->success()
                        ->title(__('filament.dashboard.add_widget_added'))
                        ->send();

                    $this->js("sessionStorage.setItem('phpvms:dashboard-editing', '1'); window.location.reload();");
                }),
            Action::make('saveDashboardLayout')
                ->label(__('filament.dashboard.save_layout'))
                ->icon(Phosphor::CheckLight)
                ->color('primary')
                ->visible(static::canEdit())
                ->alpineClickHandler("window.dispatchEvent(new CustomEvent('dashboard-layout:save'))")
                ->extraAttributes([
                    'class'                      => 'hidden',
                    'data-dashboard-layout-save' => '',
                ]),
        ];
    }

    /**
     * Widget type => label options for the "Add widget" picker, excluding
     * types already placed on the current dashboard.
     *
     * @return array<string, string>
     */
    private function getAddableWidgetOptions(): array
    {
        $dashboard = $this->getCurrentDashboard();

        if (!$dashboard instanceof DashboardModel) {
            return [];
        }

        $existingTypes = $dashboard->widgets()->pluck('type')->all();

        return array_diff_key($this->getAvailableWidgetOptions(), array_flip($existingTypes));
    }

    private function createDefaultWidgets(DashboardModel $dashboard): void
    {
        foreach (self::DEFAULT_WIDGETS as $widget) {
            $this->createDashboardWidget($dashboard, $widget);
        }
    }

    private function upgradeLegacyStatsWidget(DashboardModel $dashboard): void
    {
        $legacyStatsWidget = $dashboard->widgets()
            ->where('type', self::LEGACY_STATS_WIDGET)
            ->first();

        if ($legacyStatsWidget === null) {
            return;
        }

        DB::transaction(function () use ($dashboard, $legacyStatsWidget): void {
            $legacyStatsWidget->delete();

            foreach (array_slice(self::DEFAULT_WIDGETS, 0, 5) as $widget) {
                $widget['y'] = $legacyStatsWidget->y;
                $this->createDashboardWidget($dashboard, $widget);
            }
        });
    }

    /** @param array{name: string, type: class-string<DynamicWidget>, x: int, y: int} $widget */
    private function createDashboardWidget(DashboardModel $dashboard, array $widget): void
    {
        DashboardWidget::query()->create([
            'dashboard_id'  => $dashboard->id,
            'name'          => $widget['name'],
            'type'          => $widget['type'],
            'section_slug'  => 'main',
            'x'             => $widget['x'],
            'y'             => $widget['y'],
            'w'             => $widget['type']::getDynamicDashboardDefaultWidth(),
            'h'             => $widget['type']::getDynamicDashboardDefaultHeight(),
            'is_active'     => true,
            'display_title' => false,
            'settings'      => [],
        ]);
    }
}
