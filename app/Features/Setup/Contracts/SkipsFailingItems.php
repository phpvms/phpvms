<?php

declare(strict_types=1);

namespace App\Features\Setup\Contracts;

/**
 * Optional for a {@see PostUpgradeTask}: called after repeated failures at the same position.
 */
interface SkipsFailingItems
{
    /**
     * Redo the failing unit item by item, record and skip the items that still throw, and move on.
     * Throws when nothing in the unit succeeds, so an outage is not mistaken for bad items.
     */
    public function skipFailing(): void;
}
