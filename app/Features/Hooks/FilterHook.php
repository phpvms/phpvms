<?php

declare(strict_types=1);

namespace App\Features\Hooks;

/**
 * A hook point whose listeners may return a changed value.
 *
 * Each listener receives this object with `$value` set to the result of the one
 * before, and returns the new value. A result the hook does not accept is
 * dropped, and the chain continues with the value as it was.
 */
abstract class FilterHook extends Hook
{
    public function __construct(public mixed $value) {}

    /**
     * Whether a listener's return value may replace the current value.
     */
    abstract public function accepts(mixed $value): bool;
}
