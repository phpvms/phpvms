<?php

namespace App\Http\Presenters;

use App\Models\Flight;
use App\Support\Units\Time;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * FlightsPresenter — dual-projection presenter for the flight schedule page.
 *
 * The controller already assembles the full Blade view-model (search options,
 * paginator, saved bids, feature flags). This presenter takes that array as-is:
 *   - toBladeArray()   → returns it verbatim (the seven Blade path is unchanged)
 *   - toInertiaArray() → projects the flat DTO the SPA Flights.vue needs
 *
 * Keeping the Blade shape byte-identical avoids any risk to existing/3rd-party
 * Blade themes while the SPA reads its own slim projection.
 */
class FlightsPresenter
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
        /** @var LengthAwarePaginator $paginator */
        $paginator = $this->data['flights'];

        /** @var array<string, int> $saved flight_id => bid_id */
        $saved = $this->data['saved'] ?? [];

        return [
            'flights' => collect($paginator->items())
                ->map(fn (Flight $f) => $this->projectFlight($f, $saved))
                ->values()
                ->all(),

            'page' => [
                'current' => $paginator->currentPage(),
                'last'    => $paginator->lastPage(),
                'total'   => $paginator->total(),
            ],

            'filters' => [
                'depIcao'      => $this->data['dep_icao'] ?? null,
                'arrIcao'      => $this->data['arr_icao'] ?? null,
                'flightNumber' => $this->data['flight_number'] ?? null,
                'flightType'   => $this->data['flight_type'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, int>  $saved
     * @return array<string, mixed>
     */
    protected function projectFlight(Flight $f, array $saved): array
    {
        return [
            'id'         => $f->id,
            'callsign'   => $f->ident,
            'dpt'        => $f->dpt_airport?->icao,
            'arr'        => $f->arr_airport?->icao,
            'distanceNm' => $f->distance ? (int) round($f->distance->toUnit('nmi')) : null,
            'blockTime'  => $f->flight_time ? Time::minutesToTimeString((int) $f->flight_time) : null,
            'type'       => $f->flight_type?->getLabel(),
            'airline'    => $f->airline ? ['icao' => $f->airline->icao, 'name' => $f->airline->name] : null,
            'bidId'      => $saved[$f->id] ?? null,
        ];
    }
}
