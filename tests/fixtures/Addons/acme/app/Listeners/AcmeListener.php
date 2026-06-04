<?php

declare(strict_types=1);

namespace PhpvmsAddonFixture\Listeners;

use PhpvmsAddonFixture\Events\AcmeFixtureEvent;

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
