<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\RecomputeBundleVisibility;
use App\Models\FlightBundle;

class BundleObserver
{
    /**
     * Dispatch a recompute for newly-created bundles so their initial flight
     * visibility settles even if the nightly cron hasn't fired yet.
     */
    public function created(FlightBundle $bundle): void
    {
        RecomputeBundleVisibility::dispatch($bundle->id);
    }

    /**
     * Dispatch a recompute when visibility-relevant fields change on an
     * existing bundle. Pure renames or description edits skip dispatch.
     */
    public function updated(FlightBundle $bundle): void
    {
        if ($bundle->wasChanged(['enabled', 'start_date', 'end_date'])) {
            RecomputeBundleVisibility::dispatch($bundle->id);
        }
    }

    /**
     * Dispatch a recompute when a soft-deleted bundle is restored so its child
     * flights flip back to their correct visible state.
     */
    public function restored(FlightBundle $bundle): void
    {
        RecomputeBundleVisibility::dispatch($bundle->id);
    }

    /**
     * Dispatch a recompute when a bundle is soft-deleted so child flights flip
     * to visible=false (the cron treats soft-deleted bundles as not enabled).
     */
    public function deleted(FlightBundle $bundle): void
    {
        if ($bundle->isForceDeleting()) {
            return;
        }

        RecomputeBundleVisibility::dispatch($bundle->id);
    }
}
