<?php

declare(strict_types=1);

namespace App\Services\RouteForge;

use App\Enums\FlightType;
use App\Models\Airline;
use App\Models\Event;
use App\Models\FlightBundle;
use App\Models\Subfleet;
use Illuminate\Support\Collection;

/**
 * Immutable input bundle for a single lint run.
 *
 * Materialized once by the lint endpoint controller (or the commit handler)
 * and passed by reference to every rule's check() method. Rules MUST treat
 * this object as read-only; mutating any collection or model in place breaks
 * the parallel-rule semantics the LintRunner assumes.
 *
 * Row shape (one entry in $rows):
 *   [
 *     'airline_id'     => int,            // strict-dup key part
 *     'flight_number'  => int,            // strict-dup key part
 *     'route_code'     => ?string,        // strict-dup key part
 *     'route_leg'      => ?int,           // strict-dup key part
 *     'dpt_airport_id' => string,         // ICAO, uppercased
 *     'arr_airport_id' => string,         // ICAO, uppercased
 *     'dpt_timezone'   => ?string,        // IANA, may be null → L11
 *     'arr_timezone'   => ?string,        // IANA, may be null → L11
 *     'departure_time' => ?string,        // origin-local HH:MM, persists to Flight.departure_time
 *     'arrival_time'   => ?string,        // destination-local HH:MM, persists to Flight.arrival_time
 *     'distance_nm'    => int|float,      // client-computed haversine
 *     // ...flight_time, days mask, etc.
 *   ]
 */
final readonly class LintContext
{
    /**
     * @param array<int, array<string, mixed>> $rows              Generated/edited rows for the batch.
     * @param Collection<int, Subfleet>        $selectedSubfleets Eager-loaded with `aircraft` and `fares`.
     * @param array<string, mixed>             $airlineStats      Snapshot of airline stats from
     *                                                            /airline-stats endpoint:
     *                                                            [
     *                                                            'existing_active_flights_count' => int,
     *                                                            'hub_airports'                  => list<string>,
     *                                                            'home_airport'                  => string|null,
     *                                                            ]
     */
    public function __construct(
        public FlightBundle $bundle,
        public array $rows,
        public Collection $selectedSubfleets,
        public Airline $airline,
        public ?Event $event,
        public array $airlineStats,
        public ?FlightType $flightType = null,
    ) {}

    /**
     * Convenience accessor for the batch-wide row count.
     */
    public function rowCount(): int
    {
        return count($this->rows);
    }
}
