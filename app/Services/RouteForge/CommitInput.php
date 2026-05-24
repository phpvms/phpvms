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
 * Immutable input bundle for one RouteForge commit.
 *
 * The controller hydrates this from validated request data + resolved Eloquent
 * models (airline, subfleets, event) before handing it to
 * RouteForgeService::commit(). Holding the resolved models here keeps the
 * service free of repository lookups and lets toLintContext() compose a
 * LintContext for the in-transaction lint re-run without duplicating fields.
 *
 * Dual-mode bundle handling:
 *   - When $existingBundle is null, $bundle is the UNSAVED row that
 *     RouteForgeService will persist inside the commit transaction so a lint
 *     failure leaves no orphaned flight_bundles row. created_by MUST already
 *     be set on the bundle by the caller.
 *   - When $existingBundle is set, that pre-existing bundle is the attach
 *     target. RouteForgeService skips the persist step entirely and stamps
 *     the existing bundle's id onto the new flights. The $bundle field still
 *     holds an UNSAVED clone with values mirrored from the existing row so
 *     LintContext / L8 can read dates without dereferencing $existingBundle
 *     directly.
 */
final readonly class CommitInput
{
    /**
     * @param FlightBundle                     $bundle            UNSAVED bundle (create-new mode) OR an unsaved clone mirroring the existing row (attach-existing mode).
     * @param FlightBundle|null                $existingBundle    Pre-existing target bundle when attach-existing mode is active; null when creating new.
     * @param array<int, array<string, mixed>> $rows              Submitted row payload. Row shape matches LintContext docblock.
     * @param Airline                          $airline           Batch-wide airline (drives row airline_id validation upstream).
     * @param Collection<int, Subfleet>        $selectedSubfleets Eager-loaded with `aircraft` + `fares` for lint and fare attach.
     * @param Event|null                       $event             Optional event association; null when no event picked.
     * @param list<int>                        $subfleetIds       Subfleet IDs to attach to every created flight via `flight_subfleet`.
     * @param string|null                      $fareMultiplier    Percent-string ("+10%", "-5%", "20%") stamped into `flight_fare.price` for each inherited subfleet fare. Null/empty = pure subfleet inheritance, no flight_fare rows created.
     * @param FlightType|null                  $flightType        Batch-wide flight type (mirrors $rows[*]['flight_type']).
     * @param array<string, mixed>             $airlineStats      L1 snapshot: existing_active_flights_count, hub_airports, home_airport.
     * @param int|null                         $causerId          User id stamped onto the activity log entry as `causer_id`. Resolved by the controller from the authenticated request; null when no user context (e.g. system commits, tests bypassing auth).
     */
    public function __construct(
        public FlightBundle $bundle,
        public ?FlightBundle $existingBundle,
        public array $rows,
        public Airline $airline,
        public Collection $selectedSubfleets,
        public ?Event $event,
        public array $subfleetIds,
        public ?string $fareMultiplier,
        public ?FlightType $flightType,
        public array $airlineStats,
        public ?int $causerId = null,
    ) {}

    /**
     * Build the LintContext used for the in-transaction lint re-run.
     *
     * The unsaved $bundle is passed through so date-overlap rules (L8) can
     * read start_date/end_date without needing a persisted id. LintRunner
     * doesn't dereference $bundle->id.
     */
    public function toLintContext(): LintContext
    {
        return new LintContext(
            bundle: $this->bundle,
            rows: $this->rows,
            selectedSubfleets: $this->selectedSubfleets,
            airline: $this->airline,
            event: $this->event,
            airlineStats: $this->airlineStats,
            flightType: $this->flightType,
        );
    }
}
