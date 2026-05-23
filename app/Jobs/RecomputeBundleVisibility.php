<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Cron\Nightly\SetVisibleFlights;
use App\Models\FlightBundle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued visibility recompute for a single bundle and its child flights.
 *
 * Dispatched by BundleObserver whenever a bundle's enabled / dates / deletion
 * state changes so admin actions take effect without waiting for nightly cron,
 * but without blocking the admin save request.
 */
class RecomputeBundleVisibility implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $bundleId) {}

    public function handle(): void
    {
        $bundle = FlightBundle::withTrashed()->find($this->bundleId);

        if (!$bundle instanceof FlightBundle) {
            return;
        }

        SetVisibleFlights::runForBundle($bundle);
    }
}
