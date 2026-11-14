<?php

declare(strict_types=1);

namespace PhpvmsAddonFixture\Hooks;

use App\Contracts\Addons\Attributes\AddonListener;
use App\Features\Hooks\Attributes\HookPriority;
use App\Features\Hooks\Listeners\GateListener;

#[AddonListener]
#[HookPriority(40)]
class AcmeGateListener extends GateListener
{
    public function check(AcmeGate $gate): void
    {
        $gate->deny('Acme says no');
    }
}
