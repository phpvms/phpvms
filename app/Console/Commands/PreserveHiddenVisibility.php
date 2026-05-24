<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Flight;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('One-shot: disable flights that were previously hidden (visible=false), preserving admin intent post-visibility-rename')]
#[Signature('phpvms:preserve-hidden-visibility')]
class PreserveHiddenVisibility extends Command
{
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
