<?php

namespace App\Http\Presenters;

use App\Models\Bid;
use App\Support\Units\Time;
use Illuminate\Support\Collection;

/**
 * BidsPresenter — dual-projection presenter for the "My Bids" page.
 *
 * The controller assembles the full Blade view-model for the bids page (user,
 * saved bids, flights collection, feature flags). This presenter takes that
 * array as-is:
 *   - toBladeArray()   → returns it verbatim (the legacy Blade path is unchanged)
 *   - toInertiaArray() → projects the flat DTO the SPA Flights/Bids.vue needs
 *
 * The Blade projection is additive-only (it returns the controller's view-model
 * verbatim and the Inertia projection adds a `bids` key), so existing/3rd-party
 * Blade themes (which read $flights/$saved/etc.) are unaffected while the SPA
 * reads its own slim projection built from the eager-loaded Bid models.
 */
class BidsPresenter
{
    /** @param array<string, mixed> $data the controller's Blade view-model */
    public function __construct(protected array $data) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): static
    {
        return new static($data);
    }

    /** @return array<string, mixed> */
    public function toBladeArray(): array
    {
        return $this->data;
    }

    /** @return array<string, mixed> */
    public function toInertiaArray(): array
    {
        /** @var Collection<int, Bid> $bids */
        $bids = collect($this->data['bids'] ?? []);

        // Flight::ident reads the airline relation. The controller already
        // eager-loads bids.flight.airline, so this loadMissing('airline') is a
        // defensive belt-and-suspenders: it is a no-op when the relation is
        // present, and guards against a LazyLoadingViolation should a caller
        // build the DTO without that eager-load. Works regardless of the outer
        // collection type.
        $flights = $bids->map(fn (Bid $b) => $b->flight)->filter();
        if ($flights->isNotEmpty()) {
            \Illuminate\Database\Eloquent\Collection::make($flights->all())->loadMissing('airline');
        }

        return [
            'bids' => collect($bids)
                ->map(fn (Bid $bid) => $this->projectBid($bid))
                ->values()
                ->all(),

            'acarsPlugin' => (bool) ($this->data['acars_plugin'] ?? false),
        ];
    }

    /**
     * Project one bid + its flight into a flat, JSON-safe row DTO. Relations are
     * eager-loaded by the controller (Bid::with('flight')) so this never lazy-
     * loads. `flight` is null-guarded for defensiveness even though the
     * controller prunes orphan bids.
     *
     * @return array<string, mixed>
     */
    protected function projectBid(Bid $bid): array
    {
        $flight = $bid->flight;

        return [
            'bid' => [
                'id'          => $bid->id,
                'flightId'    => $bid->flight_id,
                'aircraftId'  => $bid->aircraft_id,
            ],
            'flight' => $flight === null ? null : [
                'id'         => $flight->id,
                'callsign'   => $flight->ident,
                'dpt'        => $flight->dpt_airport_id,
                'arr'        => $flight->arr_airport_id,
                'distanceNm' => $flight->distance ? (int) round($flight->distance->toUnit('nmi')) : null,
                'blockTime'  => $flight->flight_time ? Time::minutesToTimeString((int) $flight->flight_time) : null,
                'type'       => $flight->flight_type?->getLabel(),
            ],
        ];
    }
}
