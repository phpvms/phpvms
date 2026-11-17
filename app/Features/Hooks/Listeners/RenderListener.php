<?php

declare(strict_types=1);

namespace App\Features\Hooks\Listeners;

use App\Features\Hooks\RenderHook;

/**
 * Base class of an addon's render hook listeners in `app/Hooks`.
 *
 * Define `public function render(SomePoint $hook): void`. The type of its
 * parameter names the hook point; core reads it when the addon boots and
 * quarantines the addon when it is missing or not a render hook point.
 *
 * The method is not declared here: a child may not narrow a parameter type,
 * and the narrow type is what names the point.
 */
abstract class RenderListener
{
    final public function handle(RenderHook $hook): void
    {
        // @phpstan-ignore method.notFound (declared by the child; see the class docblock)
        $this->render($hook);
    }
}
