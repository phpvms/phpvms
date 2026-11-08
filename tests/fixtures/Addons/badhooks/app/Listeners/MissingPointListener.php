<?php

declare(strict_types=1);

namespace PhpvmsBadHooksFixture\Listeners;

use App\Features\Hooks\Points\DoesNotExist;

class MissingPointListener
{
    public function handle(DoesNotExist $gate): void {}
}
