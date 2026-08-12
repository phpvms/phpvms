<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Enums\FareType;
use App\Enums\FlightType;
use App\Models\Aircraft;
use App\Models\Flight;
use App\Models\SimBriefAttempt;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class SimBriefPlanningData extends Data
{
    /**
     * @param array<string, string|int|float|null> $providerFields
     */
    public function __construct(
        public SimBriefAttemptData $attempt,
        public FlightDetailData $flight,
        public EligibleAircraftData $aircraft,
        public array $providerFields,
        public bool $requiresExplicitGeneration,
    ) {}

    public static function fromModels(SimBriefAttempt $attempt, Flight $flight, Aircraft $aircraft, User $user): self
    {
        $fares = collect($attempt->fare_data ?? []);
        $passengerCount = (int) $fares->where('type', FareType::PASSENGER->value)->sum('count');
        $cargoLoad = (int) $fares->where('type', FareType::CARGO->value)->sum('count');
        $passengerWeight = $flight->flight_type === FlightType::CHARTER_PAX_ONLY
            ? setting('simbrief.charter_pax_weight', 168)
            : setting('simbrief.noncharter_pax_weight', 185);
        $baggageWeight = $flight->flight_type === FlightType::CHARTER_PAX_ONLY
            ? setting('simbrief.charter_baggage_weight', 28)
            : setting('simbrief.noncharter_baggage_weight', 35);
        $passengerLoad = setting('units.weight') === 'kg'
            ? (int) round(($passengerWeight * $passengerCount) / 2.205)
            : (int) round($passengerWeight * $passengerCount);
        $baggageLoad = setting('units.weight') === 'kg'
            ? (int) round(($baggageWeight * $passengerCount) / 2.205)
            : (int) round($baggageWeight * $passengerCount);
        $payload = $passengerLoad + $baggageLoad + $cargoLoad;
        $loadDistribution = $fares
            ->map(fn (array $fare): string => $fare['code'].' '.$fare['count'])
            ->implode(' ');
        $airlineIcao = $flight->airline?->icao ?? '';
        $callsign = (bool) setting('simbrief.callsign', true)
            ? $user->ident
            : $airlineIcao.(filled($flight->callsign) ? $flight->callsign : $flight->flight_number);
        $acdata = json_encode([
            'paxwgt'  => $passengerWeight,
            'bagwgt'  => $baggageWeight,
            'mzfw'    => filled($aircraft->zfw) && $aircraft->zfw->internal(0) > 0 ? round($aircraft->zfw->internal(0) / 1000, 3) : null,
            'mtow'    => filled($aircraft->mtow) && $aircraft->mtow->internal(0) > 0 ? round($aircraft->mtow->internal(0) / 1000, 3) : null,
            'mlw'     => filled($aircraft->mlw) && $aircraft->mlw->internal(0) > 0 ? round($aircraft->mlw->internal(0) / 1000, 3) : null,
            'hexcode' => filled($aircraft->hex_code) ? $aircraft->hex_code : null,
            'maxpax'  => $fares->where('type', FareType::PASSENGER->value)->sum('capacity'),
        ]);

        return new self(
            attempt: SimBriefAttemptData::fromModel($attempt),
            flight: FlightDetailData::fromModel($flight, []),
            aircraft: EligibleAircraftData::fromModel($aircraft),
            providerFields: [
                'acdata'       => $acdata,
                'airline'      => $airlineIcao,
                'altn'         => $flight->alt_airport_id ?? 'AUTO',
                'callsign'     => $callsign,
                'cargo'        => $cargoLoad > 0 ? number_format($cargoLoad / 1000, 1) : null,
                'contpct'      => '0.05/5',
                'cpt'          => (bool) setting('simbrief.name_private', true) ? $user->name_private : null,
                'cruise'       => 'CI',
                'civalue'      => 'AUTO',
                'dest'         => $flight->arr_airport_id,
                'etops'        => 0,
                'find_sidstar' => 'R',
                'firnot'       => 0,
                'fl'           => $flight->level,
                'fltnum'       => $flight->flight_number,
                'manualrmk'    => $payload > 0 ? 'Load Distribution '.$loadDistribution.' BAG '.$baggageLoad : null,
                'maps'         => 'detail',
                'navlog'       => 1,
                'notams'       => 1,
                'omit_sids'    => 0,
                'omit_stars'   => 0,
                'orig'         => $flight->dpt_airport_id,
                'pax'          => $passengerCount > 0 || $cargoLoad > 0 ? $passengerCount : null,
                'planformat'   => 'lido',
                'reg'          => $aircraft->registration,
                'resvrule'     => 30,
                'route'        => $flight->route,
                'selcal'       => $aircraft->selcal ?? 'FK-NS',
                'static_id'    => $attempt->static_id,
                'stepclimbs'   => 0,
                'tlr'          => 1,
                'type'         => $aircraft->subfleet->simbrief_type ?: $aircraft->icao,
                'units'        => setting('units.weight') === 'kg' ? 'KGS' : 'LBS',
            ],
            requiresExplicitGeneration: true,
        );
    }
}
