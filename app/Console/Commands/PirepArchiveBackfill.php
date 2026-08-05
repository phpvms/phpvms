<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\BackfillPirepsParentFlight;
use App\Models\Pirep;
use App\Services\PirepArchiveService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'pireps:archive-backfill', description: "Backfill pirep_archive rows for filed PIREPs that don't have one yet")]
#[Description("Backfill pirep_archive rows for filed PIREPs that don't have one yet")]
#[Signature('pireps:archive-backfill')]
class PirepArchiveBackfill extends Command
{
    public function handle(PirepArchiveService $archiveService, BackfillPirepsParentFlight $job): int
    {
        $pending = Pirep::whereIn('state', BackfillPirepsParentFlight::FILED_STATES)
            ->whereDoesntHave('metadata')
            ->count();

        if ($pending === 0) {
            $this->info('No filed PIREPs are missing an archive row.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Backfilling archives for %s PIREP(s)...', $pending));

        try {
            $job->handle($archiveService);
        } catch (RuntimeException $runtimeException) {
            $this->error($runtimeException->getMessage());

            return self::FAILURE;
        }

        $remaining = Pirep::whereIn('state', BackfillPirepsParentFlight::FILED_STATES)
            ->whereDoesntHave('metadata')
            ->count();

        $this->info('Archived: '.($pending - $remaining).', Skipped: '.$remaining);

        return self::SUCCESS;
    }
}
