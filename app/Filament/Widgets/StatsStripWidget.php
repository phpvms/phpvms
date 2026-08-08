<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\AircraftStatus;
use App\Enums\PirepState;
use App\Models\Aircraft;
use App\Models\Pirep;
use App\Models\User;
use Filament\Widgets\Widget;
use Override;

/**
 * Five-cell operating-figures strip. Renders first on the dashboard (see
 * App\Filament\Pages\Dashboard::content()).
 *
 * All PIREP figures use the same trailing-seven-days window as
 * ActivityCalendarWidget (today + 6 previous days).
 */
class StatsStripWidget extends Widget
{
    protected string $view = 'filament.widgets.dashboard.stats-strip';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -4;

    #[Override]
    protected function getViewData(): array
    {
        $windowStart = now()->subDays(6)->startOfDay();

        $reports = Pirep::query()
            ->where('submitted_at', '>=', $windowStart)
            ->whereNotIn('state', [PirepState::DRAFT, PirepState::IN_PROGRESS, PirepState::CANCELLED]);

        $reportsTotal = (clone $reports)->count();
        $reportsAccepted = (clone $reports)->where('state', PirepState::ACCEPTED)->count();
        $reportsPending = (clone $reports)->where('state', PirepState::PENDING)->count();

        $blockMinutes = (int) (clone $reports)->sum('flight_time');
        $avgLegMinutes = $reportsTotal > 0 ? $blockMinutes / $reportsTotal : 0;

        // Postgres returns SUM() as a numeric string; the DistanceCast is bypassed
        // by raw aggregates either way, so cast explicitly (round()/(int) both
        // require int|float under strict_types).
        $distanceTotal = (int) round((float) (clone $reports)->sum('distance'));
        $avgLegDistance = $reportsTotal > 0 ? (int) round($distanceTotal / $reportsTotal) : 0;

        $pilotsFlying = (clone $reports)->distinct('user_id')->count('user_id');
        $activePilots = User::active()->count();

        $topPilot = (clone $reports)
            ->selectRaw('user_id, COUNT(*) as legs')
            ->groupBy('user_id')
            ->orderByDesc('legs')
            ->with('user:id,name')
            ->first();

        $tailsActive = Aircraft::query()->where('status', AircraftStatus::ACTIVE)->count();
        $tailsTotal = Aircraft::query()->count();
        $tailsMaintenance = Aircraft::query()->where('status', AircraftStatus::MAINTENANCE)->count();

        return [
            'reportsTotal'     => $reportsTotal,
            'reportsAccepted'  => $reportsAccepted,
            'reportsPending'   => $reportsPending,
            'blockHours'       => round($blockMinutes / 60, 1),
            'legsCount'        => $reportsTotal,
            'avgLegHours'      => round($avgLegMinutes / 60, 1),
            'distanceTotal'    => $distanceTotal,
            'avgLegDistance'   => $avgLegDistance,
            'pilotsFlying'     => $pilotsFlying,
            'activePilots'     => $activePilots,
            'topPilotName'     => $topPilot?->user?->name,
            'topPilotLegs'     => $topPilot !== null ? (int) $topPilot->getAttribute('legs') : null,
            'tailsActive'      => $tailsActive,
            'tailsTotal'       => $tailsTotal,
            'tailsMaintenance' => $tailsMaintenance,
        ];
    }
}
