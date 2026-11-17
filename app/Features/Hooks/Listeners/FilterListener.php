<?php

declare(strict_types=1);

namespace App\Features\Hooks\Listeners;

use App\Features\Hooks\FilterHook;

/**
 * Base class of an addon's filter hook listeners in `app/Hooks`.
 *
 * Define `public function filter(SomePoint $hook): mixed`. The type of its
 * parameter names the hook point; core reads it when the addon boots and
 * quarantines the addon when it is missing or not a filter hook point (not a render point).
 *
 * The method is not declared here: a child may not narrow a parameter type,
 * and the narrow type is what names the point.
 */
abstract class FilterListener
{
    final public function handle(FilterHook $hook): mixed
    {
        // @phpstan-ignore method.notFound (declared by the child; see the class docblock)
        return $this->filter($hook);
    }
}
