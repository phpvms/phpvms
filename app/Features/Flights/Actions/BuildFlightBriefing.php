<?php

declare(strict_types=1);

namespace App\Features\Flights\Actions;

use App\Enums\BriefingItemKind;
use App\Enums\BriefingStationRole;
use App\Features\Hooks\Facades\Hooks;
use App\Features\Hooks\Points\BuildingFlightBriefing;
use App\Http\Data\BriefingTimelineItemData;
use App\Http\Data\FlightBriefingData;
use App\Http\Data\FlightBriefingOfpData;
use App\Http\Data\SimBriefBriefingData;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\SimBrief;
use App\Models\User;
use App\Services\SkylightSimBriefService;
use App\Support\Dto\SimBriefOfp\SimBriefOfp;
use App\Support\Dto\SimBriefOfp\SimBriefOfpNavlog;

/**
 * Build the pilot's briefing for a flight: a timeline of stations, route and
 * fixes, open to addon notices through the `BuildingFlightBriefing` filter.
 *
 * Only the pilot's own OFP is used. The alternate comes from the flight, and
 * from the OFP when the flight has none.
 */
final readonly class BuildFlightBriefing
{
    public function __construct(private SkylightSimBriefService $simBrief) {}

    public function handle(Flight $flight, User $user): FlightBriefingData
    {
        $flight->loadMissing([
            'dpt_airport' => fn ($query) => $query->withTrashed(),
            'arr_airport' => fn ($query) => $query->withTrashed(),
            'alt_airport' => fn ($query) => $query->withTrashed(),
        ]);

        $briefing = $this->simBrief->existingBriefingFor($user, $flight);
        $ofp = $briefing?->ofp;

        $items = [
            $this->station($flight->dpt_airport, $flight->dpt_airport_id, BriefingStationRole::Departure),
            ...($ofp instanceof SimBriefOfp ? $this->fixes($ofp) : $this->routeRow($flight)),
            $this->station($flight->arr_airport, $flight->arr_airport_id, BriefingStationRole::Arrival),
        ];

        $alternate = $this->alternate($flight, $ofp);
        if ($alternate instanceof BriefingTimelineItemData) {
            $items[] = $alternate;
        }

        /** @var list<BriefingTimelineItemData> $timeline */
        $timeline = Hooks::filter(new BuildingFlightBriefing($flight, $user, $briefing, $items));

        return new FlightBriefingData(
            timeline: $timeline,
            plannedFixes: SimBriefBriefingData::plannedFixes($ofp),
            ofp: $briefing instanceof SimBrief && $ofp instanceof SimBriefOfp ? $this->ofpSummary($briefing, $ofp) : null,
        );
    }

    private function station(?Airport $airport, ?string $icao, BriefingStationRole $role): BriefingTimelineItemData
    {
        return new BriefingTimelineItemData(
            kind: BriefingItemKind::Station,
            anchor: $icao,
            title: filled($airport?->name) ? $airport->name : (string) $icao,
            detail: $role->getLabel(),
            role: $role,
        );
    }

    /**
     * @return list<BriefingTimelineItemData>
     */
    private function routeRow(Flight $flight): array
    {
        if (!filled($flight->route)) {
            return [];
        }

        return [new BriefingTimelineItemData(
            kind: BriefingItemKind::Route,
            anchor: null,
            title: $flight->route,
        )];
    }

    private function alternate(Flight $flight, ?SimBriefOfp $ofp): ?BriefingTimelineItemData
    {
        if (filled($flight->alt_airport_id)) {
            return $this->station($flight->alt_airport, $flight->alt_airport_id, BriefingStationRole::Alternate);
        }

        $alternate = $ofp?->alternate[0] ?? null;
        if ($alternate === null) {
            return null;
        }

        return new BriefingTimelineItemData(
            kind: BriefingItemKind::Station,
            anchor: $alternate->icao_code,
            title: filled($alternate->name) ? $alternate->name : $alternate->icao_code,
            detail: BriefingStationRole::Alternate->getLabel(),
            role: BriefingStationRole::Alternate,
        );
    }

    /**
     * Navlog rows between the airports, with top of climb and descent as phases
     * and each rise in cruise level after the climb as a step climb.
     *
     * @return list<BriefingTimelineItemData>
     */
    private function fixes(SimBriefOfp $ofp): array
    {
        $rows = [];
        $cruiseFt = null;
        $descending = false;
        $hasTopOfClimb = collect($ofp->navlog)->contains(fn (SimBriefOfpNavlog $fix): bool => $fix->ident === 'TOC');

        /** @var SimBriefOfpNavlog $fix */
        foreach ($ofp->navlog as $fix) {
            if (in_array($fix->ident, [$ofp->origin->icao_code, $ofp->destination->icao_code], true)) {
                continue;
            }

            $isPhase = in_array($fix->ident, ['TOC', 'TOD'], true);
            $detail = null;

            if ($fix->ident === 'TOC') {
                $cruiseFt = $fix->altitude_feet;
            } elseif ($fix->ident === 'TOD') {
                $descending = true;
            } elseif ($cruiseFt === null && !$hasTopOfClimb && !$fix->is_sid_star) {
                $cruiseFt = $fix->altitude_feet;
            } elseif ($cruiseFt !== null && !$descending && $fix->altitude_feet > $cruiseFt) {
                $cruiseFt = $fix->altitude_feet;
                $detail = __('briefing.step_climb', ['level' => (int) ($fix->altitude_feet / 100)]);
            }

            $rows[] = new BriefingTimelineItemData(
                kind: $isPhase ? BriefingItemKind::Phase : BriefingItemKind::Fix,
                anchor: $fix->ident,
                title: match ($fix->ident) {
                    'TOC'   => __('briefing.phase.top_of_climb'),
                    'TOD'   => __('briefing.phase.top_of_descent'),
                    default => $fix->ident,
                },
                detail: $detail,
                elapsedMinutes: $this->minutes($fix->time_total),
                altitudeFt: $fix->altitude_feet,
                viaAirway: filled($fix->via_airway) ? $fix->via_airway : null,
            );
        }

        return $rows;
    }

    private function ofpSummary(SimBrief $briefing, SimBriefOfp $ofp): FlightBriefingOfpData
    {
        return new FlightBriefingOfpData(
            briefingId: (string) $briefing->id,
            briefingUrl: route('frontend.ofp.briefing', $briefing->id),
            blockFuel: $ofp->fuel->plan_ramp,
            zeroFuelWeight: $ofp->weights->est_zfw,
            weightUnit: str_starts_with(strtolower($ofp->params->units), 'k') ? 'kg' : 'lb',
            airTimeMinutes: $this->minutes($ofp->times->est_time_enroute),
            departureRunway: filled($ofp->origin->plan_rwy) ? $ofp->origin->plan_rwy : null,
            arrivalRunway: filled($ofp->destination->plan_rwy) ? $ofp->destination->plan_rwy : null,
            aircraftRegistration: filled($ofp->aircraft->reg) ? $ofp->aircraft->reg : null,
        );
    }

    /**
     * Whole minutes in a SimBrief "HH:MM:SS" duration, or in plain seconds.
     */
    private function minutes(string $duration): ?int
    {
        if (is_numeric($duration)) {
            return intdiv((int) $duration, 60);
        }

        if (!preg_match('/^(\d+):(\d{2})(?::(\d{2}))?$/', $duration, $found)) {
            return null;
        }

        return ((int) $found[1]) * 60 + (int) $found[2];
    }
}
