<?php

declare(strict_types=1);

namespace PhpvmsAddonFixture\Listeners;

use App\Contracts\Addons\Attributes\AddonListener;
use App\Features\Hooks\Attributes\HookPriority;
use PhpvmsAddonFixture\Hooks\AcmeGate;

#[AddonListener]
#[HookPriority(40)]
class AcmeGateListener
{
    public function handle(AcmeGate $gate): void
    {
        $gate->deny('Acme says no');
    }
}
