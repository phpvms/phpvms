<?php

declare(strict_types=1);

namespace App\Addons;

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
     * Loaded from the image root, so nothing could be removed: the files belong
     * to the image layer and the next deploy restores them. Disabled instead,
     * with its row and files intact.
     */
    case Disabled = 'disabled';

    /**
     * A runtime copy was shadowing a version the image still ships. The copy is
     * gone and the row now points at the bundled version, which keeps running —
     * a revert, not an uninstall, so tables and settings were left alone.
     */
    case Reverted = 'reverted';
}
