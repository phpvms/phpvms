<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\BackfillPirepArchives;
use App\Models\Pirep;
use App\Services\PirepArchiveService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'pireps:archive-backfill', description: "Backfill pirep_archive rows for filed PIREPs that don't have one yet")]
#[Description("Backfill pirep_archive rows for filed PIREPs that don't have one yet")]
#[Signature('pireps:archive-backfill')]
class ArchiveBackfillPireps extends Command
{
    public function handle(PirepArchiveService $archiveService, BackfillPirepArchives $job): int
    {
        $pending = Pirep::whereIn('state', BackfillPirepArchives::FILED_STATES)
            ->whereDoesntHave('archive')
            ->count();

        if ($pending === 0) {
            $this->info('No filed PIREPs are missing an archive row.');

            return self::SUCCESS;
        }

        $this->info("Backfilling archives for {$pending} PIREP(s)...");

        $job->handle($archiveService);

        $remaining = Pirep::whereIn('state', BackfillPirepArchives::FILED_STATES)
            ->whereDoesntHave('archive')
            ->count();

        $this->info('Archived: '.($pending - $remaining).', Skipped: '.$remaining);

        return self::SUCCESS;
    }
}
