<?php

declare(strict_types=1);

namespace App\Features\Hooks;

use Closure;

/**
 * A hook point where listeners add content to a page.
 *
 * `$value` is a list of {@see Fill}. A listener changes it with `add()`,
 * `replace()` or `remove()` and returns nothing; the dispatcher ignores any
 * return value and reads `$value` after the call. Core renders the fills of the
 * final list. If a listener leaves `$value` as anything but a list of `Fill`,
 * the dispatcher drops that listener's change and the previous list stands.
 */
abstract class RenderHook extends FilterHook
{
    public function __construct()
    {
        parent::__construct([]);
    }

    public function accepts(mixed $value): bool
    {
        return is_array($value) && array_is_list($value) && array_all($value, fn ($fill): bool => $fill instanceof Fill);
    }

    /**
     * Append fills after the current ones.
     */
    public function add(Fill ...$fills): void
    {
        $this->value = [...$this->value, ...$fills];
    }

    /**
     * Set the list to exactly these fills.
     */
    public function replace(Fill ...$fills): void
    {
        $this->value = $fills;
    }

    /**
     * Drop every fill for which `$match` returns true.
     *
     * @param Closure(Fill): bool $match
     */
    public function remove(Closure $match): void
    {
        $this->value = array_values(array_filter($this->value, fn (Fill $fill): bool => !$match($fill)));
    }
}
