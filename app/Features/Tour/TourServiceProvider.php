<?php

declare(strict_types=1);

namespace App\Features\Tour;

use App\Events\PirepCancelled;
use App\Events\PirepDiverted;
use App\Events\PirepPrefiled;
use App\Events\PirepRejected;
use App\Models\Pirep;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the tour slice to the PIREP lifecycle.
 *
 * Registered here rather than discovered: `withEvents()` in `bootstrap/app.php`
 * scans `app/Cron` and `app/Listeners` only, and the deletion hook is an
 * Eloquent model event, which discovery cannot see at all.
 *
 * Filing a leg is deliberately absent — `PirepEventsSubscriber::handlePirepFiled`
 * calls `advance()` itself, because it has to run after `handleDiversion()`.
 */
class TourServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Points user_tours.pirep_id at the IN_PROGRESS PIREP, so the column is
        // populated while the pilot is airborne rather than only once they file.
        Event::listen(PirepPrefiled::class, function (PirepPrefiled $event): void {
            $this->tours()->attachPirep($event->pirep);
        });

        // A tour is a chain: a leg that leaves the filed-and-accepted path
        // leaves the aircraft somewhere the remaining legs cannot depart from.
        //
        // PirepDiverted is listed for completeness, but nothing dispatches it
        // today — PirepService imports the broadcast notification of the same
        // name (`PirepService.php:43`) and fires that at `:836` instead. The
        // diversion is caught in PirepEventsSubscriber::handlePirepFiled.
        Event::listen(
            [PirepDiverted::class, PirepRejected::class, PirepCancelled::class],
            function (PirepDiverted|PirepRejected|PirepCancelled $event): void {
                $this->tours()->cancelForPirep($event->pirep);
            },
        );

        // There is no PirepDeleted event, and Filament's delete actions never
        // reach PirepService::delete(), so the model event is the only hook that
        // catches every path. Fires for soft and force deletes alike.
        Pirep::deleting(function (Pirep $pirep): void {
            $this->tours()->cancelForPirep($pirep);
        });
    }

    private function tours(): TourService
    {
        return $this->app->make(TourService::class);
    }
}
