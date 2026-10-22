<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Pirep;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Override;

/**
 * How many pilots are in the air right now.
 *
 * Deliberately not scoped to the page's date filter: "flying" is a fact about
 * this moment, and a period filter would only ever make the number wrong. Live
 * membership comes from `Pirep::onLiveMap()`, the same scope the Live Flights
 * page and the public map read, so the three cannot disagree about who is up.
 */
class PilotsFlyingStatWidget extends DashboardStatWidget
{
    public static function getWidgetLabel(): string
    {
        return __('filament.dashboard.pilots_flying');
    }

    public static function getDynamicDashboardDefaultWidth(): int
    {
        return 3;
    }

    #[Override]
    protected function getViewData(): array
    {
        $airlines = $this->filterAirlines();

        $live = fn (): Builder => Pirep::onLiveMap()
            ->when(
                filled($airlines),
                fn (Builder $query): Builder => $query->whereIn('pireps.airline_id', $airlines),
            );

        // `user_id` exists on both `pireps` and `pirep_positions`, and
        // `onLiveMap()` joins them — the column has to be qualified or the
        // count is ambiguous.
        $pilotsFlying = $live()->distinct()->count('pireps.user_id');
        $flightCount = $live()->count();

        return [
            'label'  => static::getWidgetLabel(),
            'value'  => number_format($pilotsFlying),
            'suffix' => 'of '.number_format(User::active()->count()),
            'note'   => $flightCount > 0
                ? $flightCount.' '.($flightCount === 1 ? 'flight' : 'flights').' on the map'
                : null,
            'icon'     => 'phosphor-users-light',
            'accent'   => 'rose',
            'noteIcon' => null,
        ];
    }
}
