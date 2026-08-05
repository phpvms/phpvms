<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Service;
use App\Enums\PirepState;
use App\Models\Aircraft;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\PirepArchive;
use App\Models\Subfleet;
use App\Support\Dto\SimBriefOfp\SimBriefOfp;
use App\Support\Dto\SimBriefOfp\SimBriefOfpNavlog;

/**
 * Builds and persists the self-contained `pirep_archive` snapshot for a filed
 * PIREP: the flight scalars + custom fields, the aircraft + subfleet, a
 * trimmed SimBrief plan, and the navlog. Sources that don't resolve leave
 * their column null — see openspec/changes/pirep-archive/design.md for the shape.
 */
class PirepArchiveService extends Service
{
    /**
     * Build and upsert the archive row for a PIREP. Safe to call repeatedly;
     * re-filing replaces the existing row rather than duplicating it.
     *
     * @param array{flight: ?array, aircraft: ?array, simbrief: ?array, navlog: ?array}|null $data
     */
    public function save(Pirep $pirep, ?array $data = null): PirepArchive
    {
        return PirepArchive::updateOrCreate(
            ['pirep_id' => $pirep->id],
            array_merge(['flight_id' => $pirep->flight_id], $data ?? $this->build($pirep)),
        );
    }

    /**
     * @return array{flight: ?array, aircraft: ?array, simbrief: ?array, navlog: ?array}
     */
    public function build(Pirep $pirep, ?PirepArchiveSources $sources = null): array
    {
        $data = [
            'flight'   => null,
            'aircraft' => null,
            'simbrief' => null,
            'navlog'   => null,
        ];

        $flight = $pirep->flight_id
            ? ($sources?->flights[$pirep->flight_id] ?? Flight::withTrashed()->find($pirep->flight_id))
            : null;
        if ($flight !== null) {
            $data['flight'] = $this->buildFlight($flight);
        }

        $aircraft = $pirep->aircraft_id
            ? ($sources?->aircraft[$pirep->aircraft_id] ?? Aircraft::withTrashed()->find($pirep->aircraft_id))
            : null;
        if ($aircraft !== null) {
            $data['aircraft'] = $this->buildAircraft($aircraft, $pirep, $sources);
        }

        $ofp = $pirep->simbrief?->ofp;
        if ($ofp !== null) {
            $data['simbrief'] = $this->buildSimBrief($ofp);
            $data['navlog'] = $this->buildNavlog($ofp);
        }

        return $data;
    }

    /**
     * @return array{callsign: ?string, alt_airport_id: ?string, flight_type: ?string,
     *     dpt_time: ?string, arr_time: ?string, flight_time: ?int, load_factor: ?float,
     *     load_factor_variance: ?float, pilot_pay: ?float, fields: array<string, mixed>}
     */
    private function buildFlight(Flight $flight): array
    {
        return [
            'callsign'             => $flight->callsign,
            'alt_airport_id'       => $flight->alt_airport_id,
            'flight_type'          => $flight->flight_type->value,
            'dpt_time'             => $flight->dpt_time,
            'arr_time'             => $flight->arr_time,
            'flight_time'          => $flight->flight_time,
            'load_factor'          => $flight->load_factor,
            'load_factor_variance' => $flight->load_factor_variance,
            'pilot_pay'            => $flight->pilot_pay,
            'fields'               => $flight->field_values->pluck('value', 'slug')->all(),
        ];
    }

    /**
     * @return array{registration: ?string, name: ?string, icao: ?string, iata: ?string,
     *     fin: ?string, simbrief_type: ?string, mtow: mixed, zfw: mixed, flight_time: int,
     *     subfleet: ?array{name: ?string, type: ?string, airline_id: ?int}}
     */
    private function buildAircraft(Aircraft $aircraft, Pirep $pirep, ?PirepArchiveSources $sources = null): array
    {
        $subfleet = $aircraft->subfleet_id
            ? ($sources?->subfleets[$aircraft->subfleet_id] ?? Subfleet::withTrashed()->find($aircraft->subfleet_id))
            : null;

        return [
            'registration'  => $aircraft->registration,
            'name'          => $aircraft->name,
            'icao'          => $aircraft->icao,
            'iata'          => $aircraft->iata,
            'fin'           => $aircraft->fin,
            'simbrief_type' => $subfleet?->simbrief_type,
            'mtow'          => $aircraft->getRawOriginal('mtow') === null ? null : (float) $aircraft->getRawOriginal('mtow'),
            'zfw'           => $aircraft->getRawOriginal('zfw') === null ? null : (float) $aircraft->getRawOriginal('zfw'),
            'flight_time'   => $this->aircraftFlightTimeBefore($aircraft, $pirep),
            'subfleet'      => $subfleet === null ? null : [
                'name'       => $subfleet->name,
                'type'       => $subfleet->type,
                'airline_id' => $subfleet->airline_id,
            ],
        ];
    }

    /**
     * Sum of flight_time (minutes) for this aircraft's ACCEPTED PIREPs filed
     * before this one, so a backfilled archive reflects the hours on the
     * airframe as of that pirep rather than today's running total.
     */
    private function aircraftFlightTimeBefore(Aircraft $aircraft, Pirep $pirep): int
    {
        return (int) Pirep::where('aircraft_id', $aircraft->id)
            ->where('state', PirepState::ACCEPTED)
            ->where('submitted_at', '<', $pirep->submitted_at)
            ->sum('flight_time');
    }

    /**
     * Trims the ~130KB OFP down to what's needed to show times/profiles/config.
     * The navlog (used to redraw the planned route) is archived separately —
     * it can be rather large.
     *
     * @return array{general: array, aircraft: array, times: array}
     */
    private function buildSimBrief(SimBriefOfp $ofp): array
    {
        return [
            'general' => [
                'cruise_profile'   => $ofp->general->cruise_profile,
                'climb_profile'    => $ofp->general->climb_profile,
                'descent_profile'  => $ofp->general->descent_profile,
                'reserve_profile'  => $ofp->general->reserve_profile,
                'costindex'        => $ofp->general->costindex,
                'initial_altitude' => $ofp->general->initial_altitude,
                'stepclimb_string' => $ofp->general->stepclimb_string,
                'route'            => $ofp->general->route,
                'route_distance'   => $ofp->general->route_distance,
                'passengers'       => $ofp->general->passengers,
            ],
            'aircraft' => [
                'icao_code'   => $ofp->aircraft->icao_code,
                'name'        => $ofp->aircraft->name,
                'reg'         => $ofp->aircraft->reg,
                'internal_id' => $ofp->aircraft->internal_id,
                'is_custom'   => $ofp->aircraft->is_custom,
            ],
            'times' => [
                'est_time_enroute'   => $ofp->times->est_time_enroute,
                'sched_time_enroute' => $ofp->times->sched_time_enroute,
                'sched_block'        => $ofp->times->sched_block,
                'est_block'          => $ofp->times->est_block,
                'reserve_time'       => $ofp->times->reserve_time,
            ],
        ];
    }

    /**
     * @return array<int, array{ident: ?string, type: ?string, pos_lat: mixed, pos_long: mixed}>
     */
    private function buildNavlog(SimBriefOfp $ofp): array
    {
        return array_map(
            static fn (SimBriefOfpNavlog $fix): array => [
                'ident'    => $fix->ident,
                'type'     => $fix->type,
                'pos_lat'  => $fix->pos_lat,
                'pos_long' => $fix->pos_long,
            ],
            $ofp->navlog,
        );
    }
}
