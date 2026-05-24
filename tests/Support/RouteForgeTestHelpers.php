<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\FlightType;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Event;
use App\Models\FlightBundle;
use App\Services\RouteForge\LintContext;
use Illuminate\Support\Collection;

/**
 * Shared builders for RouteForge unit + feature tests.
 *
 * Pest test files autoload via the global namespace; defining `function ctx()`
 * at the top of two test files would collide. This static class is PSR-4
 * autoloaded via the `Tests\` namespace — each rule test imports
 * `use Tests\Support\RouteForgeTestHelpers as RF;` and calls `RF::ctx(...)`.
 */
final class RouteForgeTestHelpers
{
    /**
     * Build a LintContext with sensible defaults. Override only what each
     * test cares about; the rest stays a stable baseline.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param Collection<int, mixed>|null      $selectedSubfleets
     * @param array<string, mixed>             $airlineStats
     */
    public static function ctx(
        array $rows = [],
        ?Collection $selectedSubfleets = null,
        ?FlightBundle $bundle = null,
        ?Airline $airline = null,
        ?Event $event = null,
        array $airlineStats = [
            'existing_active_flights_count' => 0,
            'hub_airports'                  => [],
            'home_airport'                  => null,
        ],
        ?FlightType $flightType = null,
    ): LintContext {
        return new LintContext(
            bundle: $bundle ?? self::unsavedBundle(),
            rows: $rows,
            selectedSubfleets: $selectedSubfleets ?? new Collection(),
            airline: $airline ?? self::unsavedAirline(),
            event: $event,
            airlineStats: $airlineStats,
            flightType: $flightType,
        );
    }

    /**
     * Unsaved FlightBundle for rules that only read columns. Avoids DB hits
     * in rule tests that don't need persistence.
     *
     * @param array<string, mixed> $attrs
     */
    public static function unsavedBundle(array $attrs = []): FlightBundle
    {
        return new FlightBundle(array_merge([
            'name'    => 'Test Bundle',
            'enabled' => true,
        ], $attrs));
    }

    /**
     * Unsaved Airline; faker columns filled by the factory's definition().
     * For tests that need a persisted airline (e.g. L5 querying flights),
     * use Airline::factory()->create() directly.
     */
    public static function unsavedAirline(): Airline
    {
        return Airline::factory()->make(['id' => 1]);
    }

    /**
     * Build a single row payload with the fields the lint catalog reads.
     * Every field has a benign default so tests only set what they exercise.
     *
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function row(array $overrides = []): array
    {
        return array_merge([
            'airline_id'     => 1,
            'flight_number'  => 100,
            'route_code'     => null,
            'route_leg'      => null,
            'dpt_airport_id' => 'KSFO',
            'arr_airport_id' => 'KLAX',
            'dpt_timezone'   => 'America/Los_Angeles',
            'arr_timezone'   => 'America/Los_Angeles',
            'distance_nm'    => 337,
            'flight_time'    => 90,
        ], $overrides);
    }

    /**
     * Persist an Airport with a deterministic 4-letter alpha ICAO. The
     * default AirportFactory generates 5-char alphanumeric strings, which
     * fail `size:4, alpha` validation in BaseRouteForgeBatchRequest.
     *
     * @param array<string, mixed> $overrides
     */
    public static function airport(string $icao, array $overrides = []): Airport
    {
        return Airport::factory()->create(array_merge([
            'id'       => $icao,
            'icao'     => $icao,
            'iata'     => substr($icao, 1, 3),
            'lat'      => 37.6,
            'lon'      => -122.4,
            'timezone' => 'America/Los_Angeles',
        ], $overrides));
    }

    /** Sequential counter so per-test airports get unique 4-letter ICAOs. */
    private static int $icaoSeq = 0;

    /**
     * Generate and persist an Airport with an auto-assigned 4-letter alpha
     * ICAO ("ZAAA", "ZAAB", ...). Resets implicitly when tests refresh DB.
     */
    public static function nextAirport(array $overrides = []): Airport
    {
        $seq = self::$icaoSeq++;
        // 26^3 = 17,576 unique combinations — plenty for any test sweep.
        $letters = sprintf(
            '%c%c%c',
            ord('A') + intdiv($seq, 26 * 26) % 26,
            ord('A') + intdiv($seq, 26) % 26,
            ord('A') + $seq % 26,
        );

        return self::airport('Z'.$letters, $overrides);
    }

    /**
     * Build a valid BaseRouteForgeBatchRequest payload for the /lint and
     * /commit endpoints. Caller supplies the airline_id + airport ICAO ids;
     * the helper fills the rest with safe defaults.
     *
     * @param  array<string, mixed> $overrides Replace any top-level key.
     * @return array<string, mixed>
     */
    public static function batchPayload(int $airlineId, string $dpt, string $arr, array $overrides = []): array
    {
        return array_replace_recursive([
            'airline_id'   => $airlineId,
            'subfleet_ids' => [],
            'flight_type'  => null,
            'event_id'     => null,
            'origins'      => [$dpt],
            'destinations' => [$arr],
            'bundle'       => [
                'existing_bundle_id' => null,
                'name'               => 'Test Bundle',
                'description'        => null,
                'enabled'            => true,
                'start_date'         => null,
                'end_date'           => null,
            ],
            'rows' => [
                [
                    'airline_id'     => $airlineId,
                    'flight_number'  => 100,
                    'route_code'     => null,
                    'route_leg'      => null,
                    'dpt_airport_id' => $dpt,
                    'arr_airport_id' => $arr,
                    'dpt_time'       => '08:00',
                    'arr_time'       => '11:00',
                    'distance_nm'    => 337,
                    'flight_time'    => 90,
                ],
            ],
        ], $overrides);
    }
}
