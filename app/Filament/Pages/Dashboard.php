<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\AuthorizesAccess;
use App\Filament\Widgets\ActivityCalendarWidget;
use App\Filament\Widgets\RequiresActionWidget;
use App\Filament\Widgets\StatsStripWidget;
use App\Http\Middleware\UpdatePending;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Dashboard as FilamentDashboard;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Override;

class Dashboard extends FilamentDashboard
{
    use AuthorizesAccess;

    protected static string|array $routeMiddleware = [UpdatePending::class];

    protected static string|BackedEnum|null $navigationIcon = 'tabler-dashboard';

    /**
     * Stats strip full-width first, then a 2-1 split (calendar + a stacked
     * requires-action/landing-quality column), then every other registered
     * widget in its existing sort order/span. Built directly off the
     * vendor helpers (getWidgetsSchemaComponents(), filterVisibleWidgets())
     * rather than the default single Grid::make(getColumns()) — the mockup's
     * layout isn't expressible as one uniform column grid, and this is the
     * smallest override that still uses every visibility/property-resolution
     * step the vendor Dashboard does.
     */
    #[Override]
    public function content(Schema $schema): Schema
    {
        $placedWidgets = [
            StatsStripWidget::class,
            ActivityCalendarWidget::class,
            RequiresActionWidget::class,
        ];

        $remainingWidgets = array_values(array_filter(
            $this->getWidgets(),
            fn (string|WidgetConfiguration $widget): bool => !in_array($this->normalizeWidgetClass($widget), $placedWidgets, true),
        ));

        return $schema->components([
            ...$this->getWidgetsSchemaComponents([StatsStripWidget::class]),
            Grid::make(2)
                ->extraAttributes(['class' => 'split split--2-1'])
                ->schema([
                    ...$this->getWidgetsSchemaComponents([ActivityCalendarWidget::class]),
                    Grid::make(1)
                        ->extraAttributes(['class' => 'grid gap-3.5'])
                        ->schema($this->getWidgetsSchemaComponents([
                            RequiresActionWidget::class,
                        ])),
                ]),
            Grid::make($this->getColumns())->schema($this->getWidgetsSchemaComponents($remainingWidgets)),
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
            $segments[] = ['flag' => public_asset('/assets/global/flags/4x3/'.Str::lower($user->country).'.svg')];
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
}
