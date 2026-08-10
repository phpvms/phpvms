<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Enums\PirepPhase;
use App\Enums\PirepState;
use App\Enums\UserState;
use App\Models\Airport;
use App\Models\Pirep;
use App\Models\Rank;
use App\Models\User;
use App\Support\Units\Time;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Flat SPA projection of the pilot dashboard. SPA-only; the Blade path keeps the
 * User model (+ current_airport, last_pirep). Ports the retired DashboardPresenter
 * (gather-then-serialize) verbatim so the dashboard widgets — which read these
 * keys via `@`-ref props / page props — are unaffected.
 */
#[TypeScript]
final class DashboardData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public int $flights,
        public string $flightTimeMinutes,
        public string $transferTimeMinutes,
        public StateBadgeData $state,
        public bool $onLeave,
        public ?BalanceData $balance,
        public ?string $currentAirport,
        public ?LastPirepData $lastPirep,
        public ?RankProgressData $rank,
        public ?int $pilotScore,
        public ?float $onTimePercentage,
        public ?int $averageLandingRate,
        public RouteData $route,
    ) {}

    public static function fromUser(User $user): self
    {
        $user->loadMissing(['journal', 'rank']);

        $lastPirep = $user->last_pirep_id
            ? Pirep::with([
                'aircraft'    => fn ($q) => $q->withTrashed(),
                'arr_airport' => fn ($q) => $q->withTrashed(),
                'comments',
                'dpt_airport' => fn ($q) => $q->withTrashed(),
            ])->find($user->last_pirep_id)
            : null;

        $currentAirportId = $user->curr_airport_id ?? $user->home_airport_id;
        $currentAirportModel = $currentAirportId ? Airport::find($currentAirportId) : null;
        $metrics = self::metrics($user);

        return new self(
            id: $user->id,
            name: $user->name,
            flights: (int) $user->flights,
            flightTimeMinutes: Time::minutesToTimeString((int) ($user->flight_time ?? 0)),
            transferTimeMinutes: Time::minutesToTimeString((int) ($user->transfer_time ?? 0)),
            state: new StateBadgeData(
                label: $user->state?->getLabel() ?? '—',
                color: $user->state?->getColor() ?? 'gray',
            ),
            onLeave: $user->state === UserState::ON_LEAVE,
            balance: self::balance($user),
            currentAirport: $currentAirportId,
            lastPirep: $lastPirep ? LastPirepData::fromModel($lastPirep) : null,
            rank: self::rank($user),
            pilotScore: $metrics['pilotScore'],
            onTimePercentage: self::onTimePercentage($user),
            averageLandingRate: $metrics['averageLandingRate'],
            route: self::route($lastPirep, $currentAirportModel),
        );
    }

    private static function balance(User $user): ?BalanceData
    {
        $money = $user->journal?->balance;

        return $money === null ? null : new BalanceData(amount: (float) $money->getValue(), formatted: (string) $money);
    }

    private static function rank(User $user): ?RankProgressData
    {
        $rank = $user->rank;
        if ($rank === null) {
            return null;
        }

        $userHours = ((int) ($user->flight_time ?? 0)) / 60.0;
        if (setting('pilots.count_transfer_hours', false)) {
            $userHours += ((int) ($user->transfer_time ?? 0)) / 60.0;
        }

        $next = Rank::where('hours', '>', $rank->hours)->orderBy('hours')->first();
        if ($next === null) {
            return new RankProgressData(
                from: $rank->name,
                to: null,
                pct: 100,
                currentHours: $userHours,
                targetHours: null,
                hoursRemaining: null,
            );
        }

        $targetHours = (float) $next->hours;
        $pct = (int) round(max(0.0, min(1.0, $userHours / $targetHours)) * 100);

        return new RankProgressData(
            from: $rank->name,
            to: $next->name,
            pct: $pct,
            currentHours: $userHours,
            targetHours: $targetHours,
            hoursRemaining: max(0, $targetHours - $userHours),
        );
    }

    /** @return array{pilotScore: ?int, averageLandingRate: ?int} */
    private static function metrics(User $user): array
    {
        $metrics = Pirep::query()
            ->where('user_id', $user->id)
            ->where('state', PirepState::ACCEPTED)
            ->selectRaw('AVG(score) as pilot_score')
            ->selectRaw('AVG(NULLIF(landing_rate, 0)) as average_landing_rate')
            ->first();

        $pilotScore = $metrics?->getAttribute('pilot_score');
        $averageLandingRate = $metrics?->getAttribute('average_landing_rate');

        return [
            'pilotScore'         => $pilotScore === null ? null : (int) round((float) $pilotScore),
            'averageLandingRate' => $averageLandingRate === null ? null : (int) round((float) $averageLandingRate),
        ];
    }

    private static function onTimePercentage(User $user): ?float
    {
        $eligible = Pirep::query()
            ->where('user_id', $user->id)
            ->where('state', PirepState::ACCEPTED)
            ->whereNotNull('scheduled_arrival_at')
            ->whereNotNull('block_on_time')
            ->withExists([
                'field_values as has_diversion' => fn ($query) => $query
                    ->where('slug', 'diversion-airport')
                    ->whereNotNull('value')
                    ->where('value', '!=', ''),
            ])
            ->get(['status', 'scheduled_arrival_at', 'block_on_time']);

        if ($eligible->isEmpty()) {
            return null;
        }

        $onTime = $eligible->filter(fn (Pirep $pirep): bool => $pirep->status !== PirepPhase::DIVERTED
            && !$pirep->getAttribute('has_diversion')
            && $pirep->block_on_time->lt($pirep->scheduled_arrival_at->copy()->addMinutes(15)))->count();

        return round(($onTime / $eligible->count()) * 100, 1);
    }

    private static function route(?Pirep $lastPirep, ?Airport $currentAirportModel): RouteData
    {
        $point = static function (?object $ap): ?RoutePointData {
            if ($ap === null || $ap->icao === null || $ap->lat === null || $ap->lon === null) {
                return null;
            }

            return new RoutePointData(icao: $ap->icao, name: $ap->name, lat: (float) $ap->lat, lon: (float) $ap->lon);
        };

        if ($lastPirep && $lastPirep->dpt_airport && $lastPirep->arr_airport) {
            return new RouteData(from: $point($lastPirep->dpt_airport), to: $point($lastPirep->arr_airport));
        }

        return new RouteData(from: $point($currentAirportModel), to: null);
    }
}
