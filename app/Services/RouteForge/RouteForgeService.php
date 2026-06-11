<?php

declare(strict_types=1);

namespace App\Services\RouteForge;

use App\Jobs\RecomputeBundleVisibility;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Models\User;
use App\Services\RouteForge\Exceptions\LintFailedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orchestrates the RouteForge commit pipeline.
 *
 * Pipeline:
 *   1. Re-run the full L1–L11 lint catalog server-side. Any error severity
 *      issue aborts via LintFailedException; the controller maps that to a
 *      422 response carrying the LintReport. This is the authoritative gate —
 *      client-side lint exists for UX, not security.
 *   2. Generate the batch_id ULID once (outside the transaction so retries
 *      keep the same identifier in the audit trail).
 *   3. Inside a DB transaction:
 *        a. Either persist the unsaved FlightBundle (create-new mode) or
 *           reuse the pre-existing one (attach-existing mode).
 *        b. Build a uniform row payload via Flight::fill() (runs casts +
 *           mutators including the ICAO uppercase-trim mutators on the model)
 *           then stamp explicit `id`, `bundle_id`, and timestamps.
 *        c. Persist all flight rows in a single `Flight::insert($attrs)` —
 *           the query-builder call bypasses Eloquent model events, so no
 *           per-flight LogsActivity entries are emitted (replaces the prior
 *           `Flight::withoutEvents(...)` wrapper) and the HasNanoIds
 *           creating-hook stays suppressed (we generate ids explicitly above).
 *        d. Persist `flight_subfleet` pivot rows in one bulk insert.
 *        e. When fareMultiplier is set, persist `flight_fare` pivot rows in
 *           one bulk insert with `price` set to the verbatim multiplier
 *           string.
 *        f. Fire ONE activity log entry on the bundle carrying batch_id,
 *           count, flight_ids, and appended_to_existing.
 *   4. After the transaction commits, in attach-existing mode dispatch
 *      RecomputeBundleVisibility explicitly (BundleObserver::created does
 *      not fire because no new bundle was created). In create-new mode the
 *      observer already dispatched the job during the bundle save inside the
 *      transaction. The previously-synchronous `SetVisibleFlights::runForBundle`
 *      call is gone — visibility settlement is now uniformly delegated to
 *      the queued job (typical sub-second eventual consistency on a healthy
 *      queue, nightly cron fallback worst-case).
 *
 * NOT in scope: PHP-side row regeneration (`on_conflict='skip'` is reserved
 * for a later iteration; the `ExistingDuplicates` rule already surfaces L5
 * same-bundle errors + L12 cross-bundle warnings against fresh DB state on
 * every lint pass and inside the commit txn).
 */
final readonly class RouteForgeService
{
    public function __construct(
        private LintRunner $lintRunner,
    ) {}

    /**
     * @throws LintFailedException When server-side lint reports any error-severity issues.
     */
    public function commit(CommitInput $input): CommitResult
    {
        $report = $this->lintRunner->run($input->toLintContext());
        if (!$report->canProceed()) {
            throw new LintFailedException($report);
        }

        $batchId = (string) Str::ulid();
        $attachExisting = $input->existingBundle instanceof FlightBundle;

        /** @var array{bundle: FlightBundle, result: CommitResult} $committed */
        $committed = DB::transaction(function () use ($input, $batchId, $attachExisting): array {
            // Dual-mode: in attach-existing mode we use the pre-existing row
            // verbatim (no name/description/dates/enabled writes); in
            // create-new mode we persist the unsaved bundle as before.
            $bundle = $attachExisting
                ? $input->existingBundle
                : $input->bundle;
            \assert($bundle instanceof FlightBundle);
            if (!$attachExisting) {
                $bundle->save();
            }

            $flightAttrs = $this->buildFlightAttrs($input, $bundle);

            if ($flightAttrs !== []) {
                Flight::insert($flightAttrs);
            }

            /** @var list<string> $flightIds */
            $flightIds = array_map(
                static fn (array $attrs): string => (string) $attrs['id'],
                $flightAttrs,
            );

            $subfleetPivotRows = $this->buildSubfleetPivotRows($flightAttrs, $input->subfleetIds);
            if ($subfleetPivotRows !== []) {
                DB::table('flight_subfleet')->insert($subfleetPivotRows);
            }

            $farePivotRows = $this->buildFarePivotRows($flightAttrs, $input);
            if ($farePivotRows !== []) {
                DB::table('flight_fare')->insert($farePivotRows);
            }

            // Single bundle-level audit entry. Per-flight LogsActivity hooks
            // never fire because Flight::insert() bypasses Eloquent model
            // events at the query-builder level. `appended_to_existing`
            // distinguishes attach-existing batches in the audit trail.
            // Causer resolved from $input->causerId (stamped by the controller
            // from auth()->id()) so this service stays auth-helper-free per
            // the tests/Arch/GlobalTest http-helpers rule.
            $causer = $input->causerId !== null ? User::query()->find($input->causerId) : null;
            activity('routeforge')
                ->performedOn($bundle)
                ->causedBy($causer)
                ->withProperties([
                    'batch_id'             => $batchId,
                    'count'                => count($flightIds),
                    'flight_ids'           => $flightIds,
                    'appended_to_existing' => $attachExisting,
                ])
                ->log('routeforge.batch_created');

            return [
                'bundle' => $bundle,
                'result' => new CommitResult(
                    bundleId: $bundle->id,
                    batchId: $batchId,
                    createdCount: count($flightIds),
                    flightIds: $flightIds,
                ),
            ];
        });

        // Attach-existing mode: BundleObserver::created did not fire (no new
        // bundle persisted), so dispatch the visibility recompute explicitly.
        // Create-new mode is already covered by the observer dispatch inside
        // the transaction.
        if ($attachExisting) {
            RecomputeBundleVisibility::dispatch($committed['bundle']->id);
        }

        return $committed['result'];
    }

    /**
     * Build the array of DB-ready flight attribute rows for bulk insert.
     *
     * Each input row flows through `Flight::fill()` so Eloquent attribute
     * casts (datetime, FlightType enum, DistanceCast, ...) and mutators
     * (the new `dpt_airport_id` / `arr_airport_id` ICAO uppercase-trim) run
     * exactly once per row. The resulting `getAttributes()` payload contains
     * DB-format values ready for the query builder, since cast SET hooks
     * serialize Carbons, enums, etc. into their column representation.
     *
     * `Model::insert()` does NOT auto-populate `id`, `bundle_id`, or
     * timestamps, so we stamp them explicitly here. Keys are unioned across
     * all rows and missing keys padded with NULL so the multi-row INSERT
     * statement uses a uniform column list (the query builder takes the
     * column list from the first row).
     *
     * @return list<array<string, mixed>>
     */
    private function buildFlightAttrs(CommitInput $input, FlightBundle $bundle): array
    {
        if ($input->rows === []) {
            return [];
        }

        $now = now()->toDateTimeString();

        /** @var list<array<string, mixed>> $attrsList */
        $attrsList = [];
        foreach ($input->rows as $row) {
            /** @var array<string, mixed> $row */
            $flight = new Flight();
            $flight->fill($row);

            /** @var array<string, mixed> $attrs */
            $attrs = $flight->getAttributes();
            $attrs['id'] = Str::nanoid();
            $attrs['bundle_id'] = $bundle->id;
            $attrs['created_at'] = $now;
            $attrs['updated_at'] = $now;

            $attrsList[] = $attrs;
        }

        // Union keys across all rows and pad missing keys with NULL so the
        // multi-row INSERT generated by Flight::insert($attrsList) uses a
        // consistent column list. Query builders take the column list from
        // row 0; rows with extra keys would silently drop columns, rows
        // missing keys would mis-align bindings.
        $allKeys = [];
        foreach ($attrsList as $attrs) {
            foreach (array_keys($attrs) as $key) {
                $allKeys[$key] = true;
            }
        }

        $template = array_fill_keys(array_keys($allKeys), null);

        return array_map(
            static fn (array $attrs): array => array_replace($template, $attrs),
            $attrsList,
        );
    }

    /**
     * Build the cartesian product of flight ids × subfleet ids for the
     * `flight_subfleet` pivot table.
     *
     * @param  list<array<string, mixed>>                       $flightAttrs
     * @param  list<int>                                        $subfleetIds
     * @return list<array{flight_id: string, subfleet_id: int}>
     */
    private function buildSubfleetPivotRows(array $flightAttrs, array $subfleetIds): array
    {
        if ($flightAttrs === [] || $subfleetIds === []) {
            return [];
        }

        $rows = [];
        foreach ($flightAttrs as $attrs) {
            foreach ($subfleetIds as $subfleetId) {
                $rows[] = [
                    'flight_id'   => (string) $attrs['id'],
                    'subfleet_id' => $subfleetId,
                ];
            }
        }

        return $rows;
    }

    /**
     * Build the cartesian product of flight ids × deduped inherited fare ids
     * for the `flight_fare` pivot table, stamping the verbatim multiplier
     * string into the `price` column. FareService::getFareWithPivot parses
     * the %-suffix syntax on read; no arithmetic happens at commit time.
     *
     * Fare ids are deduped across the selected subfleets because the pivot
     * has a composite primary key on (flight_id, fare_id); the same fare
     * inherited from two different subfleets would otherwise produce a
     * duplicate-key violation.
     *
     * @param  list<array<string, mixed>>                                                                          $flightAttrs
     * @return list<array{flight_id: string, fare_id: int, price: string, created_at: string, updated_at: string}>
     */
    private function buildFarePivotRows(array $flightAttrs, CommitInput $input): array
    {
        $multiplier = $input->fareMultiplier;
        if ($flightAttrs === [] || $multiplier === null || $multiplier === '') {
            return [];
        }

        $fareIds = [];
        foreach ($input->selectedSubfleets as $subfleet) {
            foreach ($subfleet->fares as $fare) {
                $fareIds[$fare->id] = true;
            }
        }

        if ($fareIds === []) {
            return [];
        }

        $now = now()->toDateTimeString();
        $rows = [];
        foreach ($flightAttrs as $attrs) {
            foreach (array_keys($fareIds) as $fareId) {
                $rows[] = [
                    'flight_id'  => (string) $attrs['id'],
                    'fare_id'    => $fareId,
                    'price'      => $multiplier,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return $rows;
    }
}
