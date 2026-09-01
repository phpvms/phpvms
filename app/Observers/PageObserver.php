<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\NavigationTarget;
use App\Models\NavigationItem;
use App\Models\Page;

/**
 * A navigation item points at a page by kind-and-value, not by foreign key,
 * because two of the three target kinds are not rows. That buys the flexibility
 * but costs the database's own cleanup, so deleting a page has to take its
 * navigation entries with it here — otherwise the menu keeps an entry that
 * resolves to nothing.
 */
class PageObserver
{
    public function deleted(Page $page): void
    {
        NavigationItem::query()
            ->where('target_type', NavigationTarget::PAGE)
            ->where('target_value', (string) $page->id)
            ->delete();
    }
}
