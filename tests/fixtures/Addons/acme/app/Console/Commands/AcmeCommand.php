<?php

declare(strict_types=1);

namespace PhpvmsAddonFixture\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Acme fixture ping command')]
#[Signature('acme:ping')]
class AcmeCommand extends Command
{
    public function handle(): int
    {
        $this->info('pong');

        return self::SUCCESS;
    }
}
