<?php

declare(strict_types=1);

namespace PhpvmsAddonFixture\Listeners;

use App\Contracts\Addons\Attributes\AddonListener;
use PhpvmsAddonFixture\Events\AcmeFixtureEvent;

#[AddonListener]
class AcmeListener
{
    /**
     * TEST spy only — static state persists across requests in Octane;
     * never use static properties as side-effect trackers in production code.
     */
    public static bool $handled = false;

    public function handle(AcmeFixtureEvent $event): void
    {
        self::$handled = true;
    }
}
