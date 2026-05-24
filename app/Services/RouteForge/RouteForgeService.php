<?php

declare(strict_types=1);

namespace App\Services\RouteForge;

use App\Cron\Nightly\SetVisibleFlights;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Models\User;
use App\Services\RouteForge\Exceptions\LintFailedException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orchestrates the RouteForge commit pipeline (design.md Decision 10).
 *
 * Pipeline:
 *   1. Re-run the full L1–L11 lint catalog server-side. Any error severity
 *      issue aborts via LintFailedException; the controller maps that to a
 *      422 response carrying the LintReport. This is the authoritative gate —
 *      client-side lint exists for UX, not security.
 *   2. Generate the batch_id ULID once (outside the transaction so retries
 *      keep the same identifier in the audit trail).
 *   3. Inside a DB transaction:
 *        a. Persist the (unsaved) FlightBundle.
 *        b. Flight::withoutEvents block — for each row, replicate
 *           FlightObserver's ICAO uppercase-trim, assign a HashId explicitly
 *           (HashIdTrait's `creating` hook is suppressed by withoutEvents),
 *           stamp bundle_id, then Flight::create() + attach subfleets +
 *           optionally attach fare-multiplier pivots.
 *        c. Outside the withoutEvents closure (still inside the transaction)
 *           fire ONE activity log entry on the bundle carrying batch_id,
 *           count, and flight_ids. Per-flight events were suppressed in (b)
 *           so the audit trail stays clean.
 *   4. Outside the transaction, trigger SetVisibleFlights::runForBundle()
 *      synchronously so the new flights' visibility settles immediately;
 *      pilots see them without waiting for the nightly cron. The redundant
 *      RecomputeBundleVisibility job that BundleObserver::created queued is
 *      idempotent — accept the duplicate work.
 *
 * NOT in scope: PHP-side row regeneration (design pivot — see Decision 2
 * banner in tasks.md Section 4) and DuplicateChecker integration (the L5
 * lint rule already handles DB collisions as warnings; on_conflict='skip'
 * is reserved for a later iteration).
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

            /** @var list<string> $flightIds */
            $flightIds = [];

            Flight::withoutEvents(function () use ($input, $bundle, &$flightIds): void {
                foreach ($input->rows as $row) {
                    $flight = $this->createFlightRow($row, $bundle, $input);
                    $flightIds[] = $flight->id;
                }
            });

            // Single bundle-level audit entry; per-flight LogsActivity hooks
            // were suppressed inside withoutEvents above. `appended_to_existing`
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

        // Run AFTER the transaction commits so the bundle row is visible to
        // the recompute's own queries. runForBundle is synchronous and
        // idempotent with the queued RecomputeBundleVisibility job that
        // BundleObserver::created already dispatched.
        SetVisibleFlights::runForBundle($committed['bundle']);

        return $committed['result'];
    }

    /**
     * Persist a single flight row and its pivots.
     *
     * Must run inside the caller's Flight::withoutEvents closure. Replicates
     * FlightObserver behavior (uppercase/trim ICAOs) inline because the
     * observer is suppressed, and assigns the HashId explicitly because the
     * HashIdTrait `creating` hook is also suppressed.
     *
     * @param array<string, mixed> $row
     */
    private function createFlightRow(array $row, FlightBundle $bundle, CommitInput $input): Flight
    {
        // FlightObserver replication (creating hook is suppressed under withoutEvents).
        $row['dpt_airport_id'] = strtoupper(trim((string) ($row['dpt_airport_id'] ?? '')));
        $row['arr_airport_id'] = strtoupper(trim((string) ($row['arr_airport_id'] ?? '')));

        // HashIdTrait::bootHashIdTrait registers a `creating` callback that
        // assigns id if empty; that's suppressed too, so do it here.
        if (empty($row['id'])) {
            $row['id'] = Flight::createNewHashId();
        }

        $row['bundle_id'] = $bundle->id;

        // Filter to fillable so callers can't sneak in non-fillable columns
        // through the row payload (defense in depth on top of Form Request).
        $attrs = Arr::only($row, (new Flight())->getFillable());

        /** @var Flight $flight */
        $flight = Flight::create($attrs);

        if ($input->subfleetIds !== []) {
            $flight->subfleets()->attach($input->subfleetIds);
        }

        // Fare multiplier: stamp the percent-string into flight_fare.price
        // for each inherited subfleet fare. FareService::getFareWithPivot
        // parses %-suffix syntax on read; no arithmetic happens here.
        $multiplier = $input->fareMultiplier;
        if ($multiplier !== null && $multiplier !== '') {
            foreach ($input->selectedSubfleets as $subfleet) {
                foreach ($subfleet->fares as $fare) {
                    $flight->fares()->attach($fare->id, ['price' => $multiplier]);
                }
            }
        }

        return $flight;
    }
}
