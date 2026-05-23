<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Flight;
use Illuminate\Console\Command;

class PreserveHiddenVisibility extends Command
{
    protected $signature = 'phpvms:preserve-hidden-visibility';

    protected $description = 'One-shot: disable flights that were previously hidden (visible=false), preserving admin intent post-visibility-rename';

    public function handle(): int
    {
        $affected = Flight::query()
            ->where('visible', false)
            ->where('enabled', true)
            ->update(['enabled' => false]);

        $this->info(sprintf('Updated %d flight(s): set enabled=false where visible=false.', $affected));

        return self::SUCCESS;
    }
}
