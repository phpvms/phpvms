<?php

declare(strict_types=1);

namespace App\Features\Addons;

/**
 * What `AddonRegistry::delete()` actually did.
 *
 * Three outcomes, because which one happened depends on where the addon's files
 * live and the caller has to say so accurately. A single bool conflated the last
 * two, and the admin UI then told operators an addon had been "switched off and
 * stays listed" in the one case where its files had in fact just been deleted
 * and it was still running.
 */
enum AddonDeleteOutcome: string
{
    /** No addon by that name. */
    case NotFound = 'not_found';

    /** Files and row both gone. The addon is uninstalled. */
    case Removed = 'removed';

    /**
     * A symlink in the addon root (a bundled addon, or a dev checkout), so
     * nothing could be removed: its files are not in the root. Disabled
     * instead, with its row and files intact.
     */
    case Disabled = 'disabled';

    /**
     * A registry update had replaced a bundled addon's link. The update is
     * gone, the link is back and the row points at the bundled version —
     * a revert, not an uninstall, so tables and settings were left alone.
     */
    case Reverted = 'reverted';
}
