<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Airport;
use App\Models\User;
use App\Services\AirportService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Create airports for ids other rows reference but no airport row carries,
 * from the remote lookup.
 *
 * Dispatched by the airports page's "Find missing airports" drawer. One remote
 * call per id, so it runs off the request; the admin who started it gets a
 * database notification with what was added and what the lookup did not know.
 */
class PopulateMissingAirports implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * @param list<string> $airportIds
     */
    public function __construct(
        public array $airportIds,
        public ?int $notifyUserId = null,
    ) {}

    public function handle(AirportService $airportService): void
    {
        $added = [];
        $notFound = [];

        foreach ($this->airportIds as $airportId) {
            // Added since the drawer listed it (by hand, or an earlier run).
            if (Airport::withTrashed()->whereKey($airportId)->exists()) {
                continue;
            }

            if ($airportService->addMissingAirport($airportId) instanceof Airport) {
                $added[] = $airportId;
            } else {
                $notFound[] = $airportId;
            }
        }

        $this->notify($added, $notFound);
    }

    /**
     * @param list<string> $added
     * @param list<string> $notFound
     */
    private function notify(array $added, array $notFound): void
    {
        $user = $this->notifyUserId === null ? null : User::find($this->notifyUserId);

        if (!$user instanceof User) {
            return;
        }

        $body = collect([
            $added === [] ? null : __('airports.missing_added', ['ids' => implode(', ', $added)]),
            $notFound === [] ? null : __('airports.missing_not_found', ['ids' => implode(', ', $notFound)]),
        ])->filter()->implode(' ');

        Notification::make()
            ->title(trans_choice('airports.missing_populated', count($added), ['count' => count($added)]))
            ->body($body)
            ->status($notFound === [] ? 'success' : 'warning')
            ->sendToDatabase($user);
    }
}
