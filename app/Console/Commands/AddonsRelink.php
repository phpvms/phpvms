<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Addons\AddonRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('addons:relink')]
#[Description('Rebuild public asset symlinks (public/ext/{name}) for all enabled addons')]
class AddonsRelink extends Command
{
    public function handle(AddonRegistry $registry): int
    {
        $registry->relinkAssets();

        $this->info('Addon asset links rebuilt.');

        return self::SUCCESS;
    }
}
