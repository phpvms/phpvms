<?php

declare(strict_types=1);

namespace App\Features\Hooks\Listeners;

use App\Features\Hooks\GateHook;

/**
 * Base class of an addon's check hook listeners in `app/Hooks`.
 *
 * Define `public function check(SomePoint $hook): GateResponse` and return
 * `$hook->allow()` or `$hook->deny(...)`. The type of its
 * parameter names the hook point; core reads it when the addon boots and
 * quarantines the addon when it is missing or not a gate hook point.
 *
 * The method is not declared here: a child may not narrow a parameter type,
 * and the narrow type is what names the point.
 */
abstract class GateListener
{
    final public function handle(GateHook $hook): mixed
    {
        // @phpstan-ignore method.notFound (declared by the child; see the class docblock)
        return $this->check($hook);
    }
}
