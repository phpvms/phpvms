<?php

declare(strict_types=1);

namespace PhpvmsAddonFixture\Listeners;

use App\Features\Hooks\Attributes\HookPriority;
use PhpvmsAddonFixture\Hooks\AcmeGate;

#[HookPriority(40)]
class AcmeGateListener
{
    public function handle(AcmeGate $gate): void
    {
        $gate->deny('Acme says no');
    }
}
