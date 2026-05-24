<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Contracts\Controller;
use App\Enums\FlightType;
use App\Http\Requests\RouteForge\AirlineStatsRequest;
use App\Http\Requests\RouteForge\CheckDuplicatesRequest;
use App\Http\Requests\RouteForge\CommitRequest;
use App\Http\Requests\RouteForge\LintRequest;
use App\Http\Requests\RouteForge\PreviewAirportsRequest;
use App\Http\Requests\RouteForge\SubfleetsRequest;
use App\Http\Resources\RouteForge\AirlineStatsResource;
use App\Http\Resources\RouteForge\CommitResponseResource;
use App\Http\Resources\RouteForge\DuplicateCheckResource;
use App\Http\Resources\RouteForge\LintReportResource;
use App\Http\Resources\RouteForge\RouteForgeAirportResource;
use App\Http\Resources\RouteForge\RouteForgeSubfleetResource;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Event;
use App\Models\FlightBundle;
use App\Models\Subfleet;
use App\Queries\AirportSearchQueryV1;
use App\Services\RouteForge\CommitInput;
use App\Services\RouteForge\DuplicateChecker;
use App\Services\RouteForge\Exceptions\LintFailedException;
use App\Services\RouteForge\LintContext;
use App\Services\RouteForge\LintRunner;
use App\Services\RouteForge\RouteForgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Backend HTTP entry points for the RouteForge admin tool.
 *
 * Every endpoint is gated by `permission:create:flight` at the route layer
 * (see routes/web.php). The Filament page itself reuses the same Shield
 * permission (App\Filament\Pages\RouteForge::canAccess), so anyone who can
 * reach the UI can hit these endpoints.
 *
 * Endpoints:
 *
 *   GET  /preview-airports   Typeahead + optional near / max_range_nm decoration.
 *   GET  /subfleets          All subfleets for the given airline (no v1 capability filter).
 *   GET  /airline-stats      L1 capacity snapshot + hub list for the form.
 *   POST /check-duplicates   Bulk strict-4-tuple collision check for the UI.
 *   POST /lint               Full L1–L11 lint pass; returns errors + warnings.
 *   POST /commit             Atomic batch create. Re-runs lint inside the txn.
 *
 * The lint and commit endpoints share the same payload envelope; both
 * delegate to private helpers that resolve the airline/event/subfleet
 * Eloquent models and assemble a LintContext or CommitInput from validated
 * data.
 */
final class RouteForgeController extends Controller
{
    /** Earth radius in nautical miles for haversine. */
    private const float EARTH_RADIUS_NM = 3440.065;

    /**
     * Airport typeahead with optional distance / range decoration.
     *
     * Uses the shared AirportSearchQueryV1 unchanged. The two RouteForge-only
     * params (`near`, `max_range_nm`) live in PreviewAirportsRequest and
     * surface here as post-fetch decorations on each Airport: dynamic
     * `distance_from_origin_nm` + `in_subfleet_range` attributes that the
     * RouteForgeAirportResource conditionally appends.
     */
    public function previewAirports(PreviewAirportsRequest $request): JsonResponse
    {
        $query = (new AirportSearchQueryV1($request))->build();
        $limit = (int) $request->input('limit', 50);

        /** @var LengthAwarePaginator<int, Airport> $paginated */
        $paginated = $query->paginate($limit);

        $this->decorateAirportsForRouteForge(
            airports: $paginated->getCollection(),
            nearIcao: $request->filled('near') ? (string) $request->input('near') : null,
            maxRangeNm: $request->filled('max_range_nm') ? (int) $request->input('max_range_nm') : null,
        );

        return RouteForgeAirportResource::collection($paginated)->response();
    }

    /**
     * Subfleets for the given airline.
     *
     * Returns every subfleet attached to the airline, no capability filter
     * (Decision 7). Eager-loads `aircraft` so the resource can return an
     * accurate active aircraft count without N+1; Subfleet::aircraft() is
     * already scoped to AircraftStatus::ACTIVE.
     */
    public function subfleets(SubfleetsRequest $request): JsonResponse
    {
        $airlineId = (int) $request->validated('airline_id');

        $subfleets = Subfleet::query()
            ->where('airline_id', $airlineId)
            ->with('aircraft')
            ->orderBy('name')
            ->get();

        return RouteForgeSubfleetResource::collection($subfleets)->response();
    }

    /**
     * Airline-wide stats snapshot for the form sidebar.
     *
     * existing_active_flights_count: enabled, non-owner flights for the
     * airline — the denominator the L1 capacity hint compares against.
     * hub_airports: distinct hub_id from the airline's subfleets (phpvms has
     * no airline-level hub list; hubs are subfleet-level via Subfleet::home).
     * home_airport: always null in v1; no airline-level home exists.
     */
    public function airlineStats(AirlineStatsRequest $request): JsonResponse
    {
        $airline = Airline::query()->findOrFail((int) $request->validated('airline_id'));

        return (new AirlineStatsResource($this->buildAirlineStats($airline)))->response();
    }

    /**
     * Bulk duplicate-check against existing flights.
     *
     * Delegates to DuplicateChecker which uses the strict 4-tuple key
     * `(airline_id, flight_number, route_code, route_leg)` scoped to
     * `owner_type IS NULL`. Not invoked from the commit pipeline; commit
     * relies on the LintRunner (L5 warning) instead.
     */
    public function checkDuplicates(
        CheckDuplicatesRequest $request,
        DuplicateChecker $checker,
    ): JsonResponse {
        $rows = (array) $request->validated('rows');
        $duplicates = $checker->check($rows);

        return (new DuplicateCheckResource($duplicates))->response();
    }

    /**
     * Run the full L1–L11 lint catalog against the submitted batch.
     *
     * The bundle in the LintContext is constructed unsaved from the request
     * payload — date-dependent rules (L8) read start_date / end_date but no
     * rule dereferences $bundle->id.
     */
    public function lint(LintRequest $request, LintRunner $runner): JsonResponse
    {
        $validated = $request->validated();
        $ctx = $this->buildLintContext($validated);
        $report = $runner->run($ctx);

        return (new LintReportResource($report))->response();
    }

    /**
     * Commit the batch atomically.
     *
     * Builds a CommitInput from validated data + resolved models and hands
     * off to RouteForgeService. A LintFailedException (raised inside the
     * service when the in-transaction lint re-run reports errors) is caught
     * and rendered as HTTP 422 with the same LintReport envelope the /lint
     * endpoint emits, so the client can reuse one renderer for both paths.
     */
    public function commit(CommitRequest $request, RouteForgeService $service): JsonResponse
    {
        $validated = $request->validated();
        $input = $this->buildCommitInput($validated);

        try {
            $result = $service->commit($input);
        } catch (LintFailedException $lintFailedException) {
            return (new LintReportResource($lintFailedException->report))
                ->response()
                ->setStatusCode(422);
        }

        return (new CommitResponseResource($result))
            ->response()
            ->setStatusCode(201);
    }

    // ------------------------------------------------------------------ //
    // Helpers                                                             //
    // ------------------------------------------------------------------ //

    /**
     * Stamp the `distance_from_origin_nm` and `in_subfleet_range` dynamic
     * attributes onto each Airport in the paginated collection, when the
     * caller supplied a `near` ICAO and/or `max_range_nm`. RouteForge's
     * Resource picks these up and serializes them.
     *
     * Origin is loaded once; the haversine runs in PHP per row, bounded by
     * the paginator's per-page limit.
     *
     * @param Collection<int, Airport> $airports
     */
    private function decorateAirportsForRouteForge(
        Collection $airports,
        ?string $nearIcao,
        ?int $maxRangeNm,
    ): void {
        if ($nearIcao === null) {
            return;
        }

        $origin = Airport::query()->find($nearIcao);
        if ($origin === null || $origin->lat === null || $origin->lon === null) {
            return;
        }

        foreach ($airports as $airport) {
            if ($airport->lat === null) {
                continue;
            }

            if ($airport->lon === null) {
                continue;
            }

            $distance = $this->haversineNm(
                latA: (float) $origin->lat,
                lonA: (float) $origin->lon,
                latB: (float) $airport->lat,
                lonB: (float) $airport->lon,
            );

            // Use setAttribute (Eloquent's typed attribute-bag API) instead
            // of dynamic property assignment so PHPStan sees a real mechanism
            // and the value lands in $model->attributes — which Eloquent's
            // own toArray() already serializes, alongside the explicit pickup
            // in RouteForgeAirportResource.
            $airport->setAttribute('distance_from_origin_nm', round($distance, 1));

            if ($maxRangeNm !== null) {
                $airport->setAttribute('in_subfleet_range', $distance <= $maxRangeNm);
            }
        }
    }

    /**
     * Haversine distance between two lat/lon coordinates in nautical miles.
     *
     * Mirrors the formula in resources/js/admin/routeforge/lib/geo.ts so
     * client preview distances match server decoration to within rounding.
     */
    private function haversineNm(float $latA, float $lonA, float $latB, float $lonB): float
    {
        $phi1 = deg2rad($latA);
        $phi2 = deg2rad($latB);
        $dPhi = deg2rad($latB - $latA);
        $dLambda = deg2rad($lonB - $lonA);

        $a = sin($dPhi / 2) ** 2
            + cos($phi1) * cos($phi2) * sin($dLambda / 2) ** 2;
        $c = 2 * asin(min(1.0, sqrt($a)));

        return self::EARTH_RADIUS_NM * $c;
    }

    /**
     * Build a LintContext from validated request payload.
     *
     * Resolves the airline + event + subfleet models, constructs an unsaved
     * FlightBundle from `bundle.*` fields, and computes an airline stats
     * snapshot for the rules that need it. Shared by the /lint endpoint and
     * (via buildCommitInput) the /commit endpoint.
     *
     * @param array<string, mixed> $validated
     */
    private function buildLintContext(array $validated): LintContext
    {
        $airline = Airline::query()->findOrFail((int) $validated['airline_id']);

        $eventId = $validated['event_id'] ?? null;
        $event = $eventId !== null ? Event::query()->find((int) $eventId) : null;

        /** @var list<int> $subfleetIds */
        $subfleetIds = array_map(static fn ($id): int => (int) $id, (array) ($validated['subfleet_ids'] ?? []));
        $selectedSubfleets = $subfleetIds === []
            ? new Collection()
            : Subfleet::query()
                ->whereIn('id', $subfleetIds)
                ->with(['aircraft', 'fares'])
                ->get();

        $flightType = isset($validated['flight_type'])
            ? FlightType::tryFrom((string) $validated['flight_type'])
            : null;

        $bundle = $this->hydrateUnsavedBundle($validated['bundle'] ?? []);

        return new LintContext(
            bundle: $bundle,
            rows: (array) ($validated['rows'] ?? []),
            selectedSubfleets: $selectedSubfleets,
            airline: $airline,
            event: $event,
            airlineStats: $this->buildAirlineStats($airline),
            flightType: $flightType,
        );
    }

    /**
     * Build a CommitInput from validated request payload.
     *
     * Reuses buildLintContext for the LintContext-shared fields; commit-only
     * fields (fare_multiplier, subfleet_ids list) are added on top. The
     * bundle's `created_by` is stamped here so the unsaved model carries the
     * acting admin's id when RouteForgeService->commit() persists it.
     *
     * Dual-mode bundle: when `bundle.existing_bundle_id` is present, we resolve
     * the existing FlightBundle and pass it via CommitInput::$existingBundle.
     * RouteForgeService skips the persist step in that branch. The unsaved
     * $bundle still carries the existing bundle's date columns so LintContext
     * (L8) reads the right window.
     *
     * @param array<string, mixed> $validated
     */
    private function buildCommitInput(array $validated): CommitInput
    {
        $ctx = $this->buildLintContext($validated);

        $existingBundle = $this->resolveExistingBundle((array) ($validated['bundle'] ?? []));

        $bundle = $ctx->bundle;
        if (!$existingBundle instanceof FlightBundle) {
            $bundle->created_by = (int) (auth()->id() ?? 0) ?: null;
        }

        /** @var list<int> $subfleetIds */
        $subfleetIds = array_map(static fn ($id): int => (int) $id, (array) ($validated['subfleet_ids'] ?? []));

        $bundleData = (array) ($validated['bundle'] ?? []);
        // In attach-existing mode the fare_multiplier input is hidden by the
        // v1 UI; if a client somehow submits one we still honor it because
        // fare_multiplier is per-batch, not per-bundle (Decision 9).
        $fareMultiplier = isset($bundleData['fare_multiplier']) && $bundleData['fare_multiplier'] !== ''
            ? (string) $bundleData['fare_multiplier']
            : null;

        return new CommitInput(
            bundle: $bundle,
            existingBundle: $existingBundle,
            rows: $ctx->rows,
            airline: $ctx->airline,
            selectedSubfleets: $ctx->selectedSubfleets,
            event: $ctx->event,
            subfleetIds: $subfleetIds,
            fareMultiplier: $fareMultiplier,
            flightType: $ctx->flightType,
            airlineStats: $ctx->airlineStats,
        );
    }

    /**
     * Resolve the attach-existing target if `bundle.existing_bundle_id` is set.
     *
     * Returns null when the field is absent or null. The Form Request already
     * validated the id with `exists:flight_bundles,id (whereNull deleted_at)`,
     * so this lookup is safe to assume non-null when the id was present.
     *
     * @param array<string, mixed> $bundleData
     */
    private function resolveExistingBundle(array $bundleData): ?FlightBundle
    {
        $existingId = $bundleData['existing_bundle_id'] ?? null;
        if ($existingId === null || $existingId === '') {
            return null;
        }

        return FlightBundle::query()->find((int) $existingId);
    }

    /**
     * Hydrate an unsaved FlightBundle from the validated `bundle` array.
     *
     * Filters to FlightBundle::$fillable so the Form Request controls which
     * fields can be set. Note: `created_by` is added by the caller (commit
     * only); lint runs against the bundle as-supplied without persistence.
     *
     * Dual-mode: when `existing_bundle_id` is set, we mirror the existing
     * row's values into the unsaved bundle so the LintContext (L8) reads
     * the right window even though the create path won't be taken.
     *
     * @param array<string, mixed> $bundleData
     */
    private function hydrateUnsavedBundle(array $bundleData): FlightBundle
    {
        $existingId = $bundleData['existing_bundle_id'] ?? null;
        if ($existingId !== null && $existingId !== '') {
            $existing = FlightBundle::query()->find((int) $existingId);
            if ($existing !== null) {
                return new FlightBundle([
                    'name'        => $existing->name,
                    'description' => $existing->description,
                    'enabled'     => $existing->enabled,
                    'start_date'  => $existing->start_date,
                    'end_date'    => $existing->end_date,
                ]);
            }
        }

        return new FlightBundle([
            'name'        => (string) ($bundleData['name'] ?? ''),
            'description' => $bundleData['description'] ?? null,
            'enabled'     => (bool) ($bundleData['enabled'] ?? false),
            'start_date'  => $bundleData['start_date'] ?? null,
            'end_date'    => $bundleData['end_date'] ?? null,
        ]);
    }

    /**
     * Compute the airline stats snapshot consumed by /airline-stats and by
     * the lint context. Shared so the same numbers surface in both places.
     *
     * @return array{existing_active_flights_count: int, hub_airports: list<string>, home_airport: string|null}
     */
    private function buildAirlineStats(Airline $airline): array
    {
        $existingActive = $airline->flights()
            ->where('enabled', true)
            ->whereNull('owner_type')
            ->count();

        /** @var list<string> $hubIcaos */
        $hubIcaos = $airline->subfleets()
            ->whereNotNull('hub_id')
            ->distinct()
            ->pluck('hub_id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();

        return [
            'existing_active_flights_count' => $existingActive,
            'hub_airports'                  => $hubIcaos,
            // Always null in v1; phpvms has no airline-level home_airport.
            'home_airport' => null,
        ];
    }
}
