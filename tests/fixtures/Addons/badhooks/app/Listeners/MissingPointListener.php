<?php

declare(strict_types=1);

namespace PhpvmsBadHooksFixture\Listeners;

use App\Contracts\Addons\Attributes\AddonListener;
use App\Hooks\Gates\DoesNotExist;

#[AddonListener]
class MissingPointListener
{
    public function handle(DoesNotExist $gate): void {}
}
