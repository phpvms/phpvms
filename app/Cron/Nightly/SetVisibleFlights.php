<?php

declare(strict_types=1);

namespace App\Cron\Nightly;

use App\Contracts\Listener;
use App\Events\CronNightly;
use App\Models\Flight;
use App\Models\FlightBundle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Nightly visibility recomputation.
 *
 * For every bundle (chunked in batches of 500), in a single iteration:
 *   1. Compute and persist `flight_bundles.visible`.
 *   2. Issue a single bulk UPDATE to set `flights.visible` for every flight in
 *      that bundle, derived from the bundle's enabled/dates state plus the
 *      flight's own enabled/dates state.
 *
 * Bulk UPDATE shapes (one per bundle):
 *   - Bundle soft-deleted or disabled → `UPDATE flights SET visible=0 WHERE bundle_id=?`
 *   - Bundle active with dates: window membership of the bundle dictates;
 *     `UPDATE flights SET visible = CASE WHEN bundle_in_window THEN enabled ELSE 0 END WHERE bundle_id=?`
 *     (collapsed to: if bundle out of window, all 0; if in window, `visible = enabled`)
 *   - Bundle active with no dates: per-flight window applies (via SQL CASE).
 */
class SetVisibleFlights extends Listener
{
    public function handle(CronNightly $event): void
    {
        Log::info('Nightly: Setting visible flights');
        self::run();
    }

    public static function run(): void
    {
        $now = Carbon::now('UTC');

        FlightBundle::query()
            ->withTrashed()
            ->chunkById(500, function ($bundles) use ($now): void {
                foreach ($bundles as $bundle) {
                    self::recompute($bundle, $now);
                }
            });
    }

    /**
     * Single-bundle recompute used by the queued RecomputeBundleVisibility job
     * after admin bundle edits.
     */
    public static function runForBundle(FlightBundle $bundle): void
    {
        self::recompute($bundle, Carbon::now('UTC'));
    }

    /**
     * Recompute and persist `bundle.visible` then bulk-update the flight rows
     * for the bundle.
     */
    private static function recompute(FlightBundle $bundle, Carbon $now): void
    {
        $bundleVisible = self::computeBundleVisible($bundle, $now);

        FlightBundle::withTrashed()
            ->whereKey($bundle->getKey())
            ->update(['visible' => $bundleVisible]);

        // Reflect into the in-memory model so downstream reads see the new value.
        $bundle->visible = $bundleVisible;

        self::applyFlightVisibilityForBundle($bundle, $now);
    }

    /**
     * Issue bulk UPDATE statements that set `flights.visible` for every flight
     * in the given bundle.
     */
    private static function applyFlightVisibilityForBundle(FlightBundle $bundle, Carbon $now): void
    {
        $bundleId = $bundle->getKey();

        // Case A: bundle is soft-deleted or disabled → all flights invisible.
        if ($bundle->deleted_at !== null || !$bundle->enabled) {
            Flight::query()
                ->where('bundle_id', $bundleId)
                ->update(['visible' => false]);

            return;
        }

        // Case B: bundle has dates → bundle window owns.
        if ($bundle->start_date !== null || $bundle->end_date !== null) {
            $bundleInWindow = self::inWindow($bundle->start_date, $bundle->end_date, $now);

            if (!$bundleInWindow) {
                Flight::query()
                    ->where('bundle_id', $bundleId)
                    ->update(['visible' => false]);

                return;
            }

            // Bundle window is open: visible = enabled.
            Flight::query()
                ->where('bundle_id', $bundleId)
                ->update(['visible' => DB::raw('enabled')]);

            return;
        }

        // Case C: bundle has no dates → flight window applies.
        // C1: flights with no start_date and no end_date → visible = enabled.
        Flight::query()
            ->where('bundle_id', $bundleId)
            ->whereNull('start_date')
            ->whereNull('end_date')
            ->update(['visible' => DB::raw('enabled')]);

        // C2: flights with a window → SQL CASE evaluates window membership.
        $nowExpr = DB::getPdo()->quote($now->toDateTimeString());
        $trueLit = self::sqlBool(true);
        $falseLit = self::sqlBool(false);

        Flight::query()
            ->where('bundle_id', $bundleId)
            ->where(function ($q): void {
                $q->whereNotNull('start_date')->orWhereNotNull('end_date');
            })
            ->update([
                'visible' => DB::raw(sprintf(
                    'CASE WHEN enabled = %s '
                    .'AND (start_date IS NULL OR start_date <= %s) '
                    .'AND (end_date IS NULL OR end_date >= %s) '
                    .'THEN %s ELSE %s END',
                    $trueLit,
                    $nowExpr,
                    $nowExpr,
                    $trueLit,
                    $falseLit,
                )),
            ]);
    }

    private static function computeBundleVisible(FlightBundle $bundle, Carbon $now): bool
    {
        if ($bundle->deleted_at !== null) {
            return false;
        }

        if (!$bundle->enabled) {
            return false;
        }

        return self::inWindow($bundle->start_date, $bundle->end_date, $now);
    }

    private static function inWindow(?Carbon $start, ?Carbon $end, Carbon $now): bool
    {
        if ($start instanceof Carbon && $now->lt($start)) {
            return false;
        }

        return !($end instanceof Carbon && $now->gt($end));
    }

    /**
     * Boolean literal portable across MySQL, Postgres, and SQLite.
     */
    private static function sqlBool(bool $value): string
    {
        return $value ? '1' : '0';
    }
}
