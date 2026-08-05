<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PirepState;
use App\Models\Aircraft;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\Subfleet;
use App\Services\PirepArchiveService;
use App\Services\PirepArchiveSources;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Backfills `pirep_archive` rows for ACCEPTED PIREPs that don't have one yet,
 * built from whatever Flight/Aircraft/SimBrief rows still resolve. Only
 * accepted PIREPs are backfilled — the others get pruned. Dispatched from the
 * admin Maintenance page; `pireps:archive-backfill` runs the same logic
 * synchronously.
 */
class BackfillPirepsParentFlight implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @var array<int, PirepState>
     */
    public const FILED_STATES = [
        PirepState::ACCEPTED,
    ];

    /**
     * Large fleets can take a while; don't let the default worker timeout
     * kill a backfill mid-run.
     */
    public int $timeout = 3600;

    public function handle(PirepArchiveService $archiveService): void
    {
        $failedIds = [];

        Pirep::whereIn('state', self::FILED_STATES)
            ->whereDoesntHave('metadata')
            ->with('simbrief')
            ->chunkById(500, function ($pireps) use ($archiveService, &$failedIds): void {
                $flightIds = $pireps->pluck('flight_id')->filter()->unique()->all();
                $aircraftIds = $pireps->pluck('aircraft_id')->filter()->unique()->all();

                $flights = Flight::withTrashed()->with('field_values')->findMany($flightIds)->keyBy('id');
                $aircraft = Aircraft::withTrashed()->findMany($aircraftIds)->keyBy('id');
                $subfleetIds = $aircraft->pluck('subfleet_id')->filter()->unique()->all();
                $subfleets = Subfleet::withTrashed()->findMany($subfleetIds)->keyBy('id');

                $sources = new PirepArchiveSources(
                    flights: $flights->all(),
                    aircraft: $aircraft->all(),
                    subfleets: $subfleets->all(),
                );

                foreach ($pireps as $pirep) {
                    try {
                        $data = $archiveService->build($pirep, $sources);
                        if (array_filter($data) === []) {
                            // Nothing resolves for this pirep; skip rather
                            // than writing an empty archive row.
                            continue;
                        }

                        $archiveService->save($pirep, $data);
                    } catch (Throwable $e) {
                        Log::error('Failed to backfill pirep archive', [
                            'pirep_id' => $pirep->id,
                            'error'    => $e->getMessage(),
                        ]);
                        $failedIds[] = $pirep->id;
                    }
                }
            });

        if ($failedIds !== []) {
            throw new RuntimeException(sprintf(
                'Failed to backfill %d pirep archive(s): %s',
                count($failedIds),
                implode(', ', array_slice($failedIds, 0, 20)),
            ));
        }
    }
}
