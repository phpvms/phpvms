<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Contracts\Controller;
use App\Enums\FlightType;
use App\Filament\Resources\FlightBundles\FlightBundleResource;
use App\Http\Requests\RouteForge\AirlineStatsRequest;
use App\Http\Requests\RouteForge\BundlesRequest;
use App\Http\Requests\RouteForge\CommitRequest;
use App\Http\Requests\RouteForge\LintRequest;
use App\Http\Requests\RouteForge\PreviewAirportsRequest;
use App\Http\Requests\RouteForge\SubfleetsRequest;
use App\Http\Resources\RouteForge\AirlineStatsResource;
use App\Http\Resources\RouteForge\CommitResponseResource;
use App\Http\Resources\RouteForge\LintReportResource;
use App\Http\Resources\RouteForge\RouteForgeAirportResource;
use App\Http\Resources\RouteForge\RouteForgeBootResource;
use App\Http\Resources\RouteForge\RouteForgeBundleResource;
use App\Http\Resources\RouteForge\RouteForgeSubfleetResource;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\FlightBundle;
use App\Models\Subfleet;
use App\Queries\AirportSearchQueryV1;
use App\Services\RouteForge\AirlineStatsService;
use App\Services\RouteForge\CommitInputFactory;
use App\Services\RouteForge\Exceptions\LintFailedException;
use App\Services\RouteForge\LintContextFactory;
use App\Services\RouteForge\LintRunner;
use App\Services\RouteForge\RouteForgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

/**
 * Backend HTTP entry points for the RouteForge admin tool.
 *
 * Every endpoint is gated by `permission:edit:flight` at the route layer
 * (see routes/web.php). The Filament page itself reuses the same
 * permission (App\Filament\Pages\RouteForge::canAccess), so anyone who can
 * reach the UI can hit these endpoints.
 *
 * Endpoints:
 *
 *   GET  /boot               One-shot SPA bootstrap envelope (CSRF, user, airlines, routes, config, translations).
 *   GET  /bundles            Paginated + searchable FlightBundle picker feed.
 *   GET  /preview-airports   Typeahead + optional near / max_range_nm decoration.
 *   GET  /subfleets          All subfleets for the given airline (no v1 capability filter).
 *   GET  /airline-stats      L1 capacity snapshot + hub list for the form.
 *   POST /lint               Full L1–L11 lint pass; returns errors + warnings.
 *   POST /commit             Atomic batch create. Re-runs lint inside the txn.
 *
 * Each endpoint is a thin HTTP mapper: validate via the bound Form Request,
 * resolve the relevant `App\Services\RouteForge\*Factory` or service, wrap
 * the result in a `Resource`, return. DTO assembly and DB lookups for the
 * lint/commit envelopes live in `LintContextFactory` / `CommitInputFactory`.
 * Airport decoration (distance, in-range flag) is owned by the
 * `RouteForgeAirportResource` itself; this controller only passes context
 * via the request attribute bag.
 *
 * The `/admin/route-forge/api/*` endpoints are session-authenticated **RPC**
 * (not REST API) — they live in routes/web.php (not routes/api.php) so the
 * cookie session + CSRF protection apply, and they have no public consumers
 * or versioned contract guarantee outside this codebase.
 */
final class RouteForgeController extends Controller
{
    /**
     * Bootstrap envelope for the RouteForge SPA — one fetch on page mount.
     *
     * Replaces the legacy `window.routeforgeConfig` global. Returns everything
     * the SPA needs to render its initial UI: CSRF token, locale, user info,
     * active airlines list, route URLs (including the templated bundle-edit
     * URL), server config, and the full `filament.routeforge` translation tree.
     *
     * Bundles are NOT in this envelope — they ship via the paginated + searchable
     * `/bundles` endpoint to keep the boot payload bounded at typical VA scale.
     *
     * Per design decision Q1 (see openspec/changes/routeforge-page-boot-via-api):
     * boot intentionally does NOT carry a version/hash field for the translation
     * sub-tree. The tree re-ships verbatim on every mount; revisit when the
     * payload exceeds ~50 KB or when a real cache-invalidation bug surfaces.
     */
    public function boot(): JsonResponse
    {
        $envelope = [
            'csrf_token'   => csrf_token(),
            'locale'       => App::getLocale(),
            'user'         => $this->buildUserPayload(),
            'airlines'     => $this->buildAirlinesPayload(),
            'routes'       => $this->buildRoutesPayload(),
            'config'       => config('phpvms.routeforge', []),
            'translations' => $this->buildTranslationsPayload(),
        ];

        return new RouteForgeBootResource($envelope)->response();
    }

    /**
     * Paginated + searchable feed of non-soft-deleted FlightBundles.
     *
     * Drives the existing-bundle picker in the SPA's BundleConfigSection. The
     * picker debounces typeahead and re-queries this endpoint per keystroke
     * instead of slurping every bundle at page mount (the legacy behavior).
     *
     * Per design decision Q2 (see openspec/changes/routeforge-page-boot-via-api):
     * this endpoint lives under `/admin/route-forge/api/bundles`, NOT under the
     * generic FlightBundle admin namespace. The RouteForge picker has tool-
     * specific filtering on its roadmap (`active-only`, `with-flight-counts`)
     * that would couple unrelated callers and force the resource to grow
     * shape-flags if hosted in a shared namespace. Trigger to relocate: a
     * second admin tool needs the same picker shape without any RouteForge-
     * specific filtering.
     *
     * Soft-deletes scope filters deleted rows automatically.
     */
    public function bundles(BundlesRequest $request): JsonResponse
    {
        $search = $request->validated('search');
        $perPage = (int) $request->validated('per_page', 25);

        $query = FlightBundle::query()->orderBy('name');

        if (is_string($search) && $search !== '') {
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where('name', $like, '%'.$search.'%');
        }

        $paginated = $query->paginate($perPage);

        return RouteForgeBundleResource::collection($paginated)->response();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUserPayload(): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        return [
            'id'         => $user->id,
            'name'       => $user->name ?? null,
            'can_commit' => $user->can('edit:flight'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildAirlinesPayload(): array
    {
        return Airline::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'icao', 'iata'])
            ->map(fn (Airline $airline): array => [
                'id'   => $airline->id,
                'name' => $airline->name,
                'icao' => $airline->icao,
                'iata' => $airline->iata,
            ])
            ->all();
    }

    /**
     * Named API URLs exposed to the SPA via the boot envelope.
     *
     * The bundle-edit URL is a template — `:id` gets substituted client-side
     * once the commit response carries `bundle_id`. We derive the path from
     * `FlightBundleResource::getUrl()` rather than hardcoding `/admin/flight-
     * bundles/...` because the resource's `$slug` is `flights`; hardcoding
     * would silently 404. The sentinel `__RF_BUNDLE_ID__` is alphanumeric so
     * Laravel's URL generator leaves it untouched, after which we swap it for
     * the `:id` placeholder the TS template expects.
     *
     * @return array<string, string>
     */
    private function buildRoutesPayload(): array
    {
        $sentinel = '__RF_BUNDLE_ID__';
        $bundleEditTemplate = str_replace(
            $sentinel,
            ':id',
            FlightBundleResource::getUrl('edit', ['record' => $sentinel]),
        );

        return [
            'preview_airports'     => route('admin.routeforge.api.preview-airports'),
            'subfleets'            => route('admin.routeforge.api.subfleets'),
            'airline_stats'        => route('admin.routeforge.api.airline-stats'),
            'lint'                 => route('admin.routeforge.api.lint'),
            'commit'               => route('admin.routeforge.api.commit'),
            'bundles'              => route('admin.routeforge.api.bundles'),
            'bundle_edit_template' => $bundleEditTemplate,
        ];
    }

    /**
     * Collect the full `filament.routeforge.*` translation tree for the SPA,
     * plus a runtime-resolved `flight_types` map keyed by IATA service-type
     * code.
     *
     * The `flight_types` sub-tree is built by iterating `FlightType::cases()`
     * and invoking `->getLabel()` on each case, which routes through
     * `__('flights.type.<semantic_name>')` and therefore honors the active
     * locale. Keying by IATA code (the enum's backing value) matches the
     * SPA call site (`t('flight_types.J')`) without forcing the TS bundle
     * to duplicate the enum's IATA→semantic mapping.
     *
     * @return array<string, mixed>
     */
    private function buildTranslationsPayload(): array
    {
        $translations = trans('filament.routeforge');
        $translations = is_array($translations) ? $translations : [];

        $flightTypes = [];
        foreach (FlightType::cases() as $case) {
            $flightTypes[$case->value] = $case->getLabel();
        }

        $translations['flight_types'] = $flightTypes;

        return $translations;
    }

    /**
     * Airport typeahead with optional distance / range decoration.
     *
     * Uses the shared `AirportSearchQueryV1` unchanged. When `near` is
     * supplied, resolve the origin Airport once and stash it (plus
     * `max_range_nm`) on the request attribute bag; the resource reads
     * those keys and computes `distance_from_origin_nm` / `in_subfleet_range`
     * per row via `App\Support\Geo::haversineNm()`.
     */
    public function previewAirports(PreviewAirportsRequest $request): JsonResponse
    {
        $query = new AirportSearchQueryV1($request)->build();
        $limit = (int) $request->input('limit', 50);

        /** @var LengthAwarePaginator<int, Airport> $paginated */
        $paginated = $query->paginate($limit);

        // The FormRequest is a separate instance from the global request
        // singleton the Resource sees during serialization. Stash decoration
        // context on the global request's attribute bag so the resource can
        // read it in toArray().
        $globalRequest = app('request');

        if ($request->filled('near')) {
            $origin = Airport::query()->find((string) $request->input('near'));
            if ($origin instanceof Airport) {
                $globalRequest->attributes->set('routeforge.origin', $origin);
            }
        }

        if ($request->filled('max_range_nm')) {
            $globalRequest->attributes->set('routeforge.max_range_nm', (int) $request->input('max_range_nm'));
        }

        return RouteForgeAirportResource::collection($paginated)->response();
    }

    /**
     * Subfleets for the given airline.
     *
     * Returns every subfleet attached to the airline, no capability filter
     * (Decision 7). Eager-loads `aircraft` so the resource can return an
     * accurate active aircraft count without N+1; `Subfleet::aircraft()` is
     * already scoped to `AircraftStatus::ACTIVE`.
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
     * Delegates the computation to `AirlineStatsService` so the same
     * snapshot is reused inside `LintContextFactory` for L1.
     */
    public function airlineStats(AirlineStatsRequest $request, AirlineStatsService $stats): JsonResponse
    {
        $airline = Airline::query()->findOrFail((int) $request->validated('airline_id'));

        return new AirlineStatsResource($stats->buildFor($airline))->response();
    }

    /**
     * Run the full L1–L11 lint catalog against the submitted batch.
     *
     * `$request->resolvedExistingBundle()` returns the pre-resolved
     * attach-existing bundle that `BaseRouteForgeBatchRequest::
     * passedValidation()` stashed during validation, or null when the
     * batch creates a new bundle. Threading it through saves a DB
     * round-trip per /lint call (which the SPA fires on every keystroke).
     */
    public function lint(LintRequest $request, LintContextFactory $contextFactory, LintRunner $runner): JsonResponse
    {
        $ctx = $contextFactory->fromValidatedPayload(
            $request->validated(),
            $request->resolvedExistingBundle(),
        );
        $report = $runner->run($ctx);

        return new LintReportResource($report)->response();
    }

    /**
     * Commit the batch atomically.
     *
     * A `LintFailedException` (raised inside the service when the
     * in-transaction lint re-run reports errors) is caught here and
     * rendered as HTTP 422 with the same `LintReport` envelope the `/lint`
     * endpoint emits, so the client can reuse one renderer for both paths.
     */
    public function commit(
        CommitRequest $request,
        CommitInputFactory $inputFactory,
        RouteForgeService $service,
    ): JsonResponse {
        $causerId = auth()->id() !== null ? (int) auth()->id() : null;
        // Same pre-resolved bundle thread-through as /lint — saves a
        // second `FlightBundle::find()` on every commit when attaching to
        // an existing bundle.
        $input = $inputFactory->fromValidatedPayload(
            $request->validated(),
            $causerId,
            $request->resolvedExistingBundle(),
        );

        try {
            $result = $service->commit($input);
        } catch (LintFailedException $lintFailedException) {
            return new LintReportResource($lintFailedException->report)
                ->response()
                ->setStatusCode(422);
        }

        return new CommitResponseResource($result)
            ->response()
            ->setStatusCode(201);
    }
}
