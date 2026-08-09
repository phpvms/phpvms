<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PirepState;
use App\Models\Pirep;
use App\Services\PirepArchiveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Backfills `pirep_archive` rows for filed PIREPs that don't have one yet,
 * built from whatever Flight/Aircraft/SimBrief rows still resolve. Dispatched
 * from the admin Maintenance page; `pireps:archive-backfill` runs the same
 * logic synchronously.
 */
class BackfillPirepArchives implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @var array<int, PirepState>
     */
    public const FILED_STATES = [
        PirepState::PENDING,
        PirepState::ACCEPTED,
        PirepState::CANCELLED,
        PirepState::REJECTED,
    ];

    /**
     * Large fleets can take a while; don't let the default worker timeout
     * kill a backfill mid-run.
     */
    public int $timeout = 3600;

    public function handle(PirepArchiveService $archiveService): void
    {
        Pirep::whereIn('state', self::FILED_STATES)
            ->whereDoesntHave('archive')
            ->chunkById(500, function ($pireps) use ($archiveService): void {
                foreach ($pireps as $pirep) {
                    try {
                        $data = $archiveService->build($pirep);
                        if ($data === []) {
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
                    }
                }
            });
    }
}
