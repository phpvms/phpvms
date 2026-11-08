<?php

declare(strict_types=1);

namespace App\Support\Extensions;

use App\Features\Hooks\Fill;

/**
 * The slot entries of the current request: `vue` fills that render hooks
 * produced for the SPA, keyed by the slot (hook point) they belong to.
 *
 * Bound `scoped`, so each request starts empty (Octane flushes scoped
 * instances between requests). Controllers fill it through `themed()`; the
 * entries reach the SPA in the shared `skylight.slots` prop.
 */
final class SlotRegistry
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $entries = [];

    /**
     * Add one fill. Anything but a `vue` fill is skipped: only those reach the SPA.
     */
    public function add(string $slot, Fill $fill, int $order): void
    {
        if ($fill->kind !== Fill::VUE) {
            return;
        }

        $this->entries[] = $fill->toSpaEntry($slot, $order);
    }

    /**
     * Add the `vue` fills of a hook's final list, ordered by list position.
     *
     * @param list<Fill> $fills
     */
    public function addAll(string $slot, array $fills): void
    {
        foreach ($fills as $index => $fill) {
            $this->add($slot, $fill, $index * 10);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->entries;
    }
}
