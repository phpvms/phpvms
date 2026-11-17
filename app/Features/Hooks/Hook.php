<?php

declare(strict_types=1);

namespace App\Features\Hooks;

use Illuminate\Support\Str;

/**
 * A hook point: a typed place where core lets addon listeners take part.
 *
 * Listeners bind to the class, never to a name, so a listener for a hook point
 * that does not exist fails when it is registered. The dotted name is only for
 * logs, `Hooks::fake()` assertions and docs.
 */
abstract class Hook
{
    /**
     * Dotted name for logs and docs. Defaults to the kebab-case class name;
     * hook points override it with a stable name, e.g. "bid.placing".
     */
    public static function name(): string
    {
        return Str::kebab(class_basename(static::class));
    }
}
