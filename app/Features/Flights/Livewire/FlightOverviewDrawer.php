<?php

declare(strict_types=1);

namespace App\Features\Flights\Livewire;

use App\Features\Bids\Actions\LoadDispatch;
use App\Features\Maps\MapView;
use App\Features\Maps\RouteMaps;
use App\Http\Data\AirportPointData;
use App\Http\Data\BidSelectionData;
use App\Http\Data\EligibleSubfleetData;
use App\Http\Data\FlightDetailData;
use App\Http\Data\FlightDispatchPolicyData;
use App\Models\Airport;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The flight overview drawer: one instance per page, opened from a manifest card.
 *
 * Open it with the Livewire event `flight-overview.open` and a `flightId`; it
 * dispatches `flight-overview.closed` with `{ flightId }` when it closes. It shows
 * the flight (route map, airports, weather, route). "Add to bids" (or "View bid"
 * when the pilot has one) closes it and opens the bid drawer with
 * `assignment-drawer.open`.
 *
 * States: idle, details, error.
 *
 * @property-read array{flight: FlightDetailData, policy: FlightDispatchPolicyData, subfleets: Collection<int, EligibleSubfleetData>, selection: ?BidSelectionData}|null $dispatchPayload
 * @property-read MapView|null $routeMap
 */
class FlightOverviewDrawer extends Component
{
    public bool $open = false;

    #[Locked]
    public ?string $flightId = null;

    public string $state = 'idle';

    public ?string $failureMessage = null;

    #[On('flight-overview.open')]
    public function show(int|string $flightId): void
    {
        $this->resetState();
        $this->flightId = (string) $flightId;
        $this->open = true;
        $this->load();
    }

    /**
     * Load the flight again; also what "Try again" runs.
     */
    public function load(): void
    {
        $this->failureMessage = null;
        unset($this->dispatchPayload, $this->routeMap);

        try {
            $payload = $this->dispatchPayload();
        } catch (ModelNotFoundException) {
            $payload = null;
        }

        $this->state = $payload === null ? 'error' : 'details';
        if ($payload === null) {
            $this->failureMessage = 'The flight could not be loaded. Check your connection and try again.';
        }

        $this->dispatch('flight-overview.loaded');
    }

    /**
     * Hand the flight to the bid drawer, only when it can be bid or is already on a bid.
     */
    public function startBid(): void
    {
        $flight = $this->dispatchPayload['flight'] ?? null;

        if ($this->state !== 'details' || $flight === null || !in_array($flight->summary->primaryAction, ['bid', 'overview'], true)) {
            return;
        }

        $flightId = $this->flightId;
        $this->close();
        $this->dispatch('assignment-drawer.open', flightId: $flightId);
    }

    public function close(): void
    {
        if ($this->flightId === null) {
            return;
        }

        $closedFlightId = $this->flightId;

        $this->resetState();
        $this->open = false;
        $this->dispatch('flight-overview.closed', flightId: $closedFlightId);
    }

    /**
     * The flight, policy, subfleets and the pilot's bid, from LoadDispatch.
     *
     * @return array{flight: FlightDetailData, policy: FlightDispatchPolicyData, subfleets: Collection<int, EligibleSubfleetData>, selection: ?BidSelectionData}|null
     */
    #[Computed]
    public function dispatchPayload(): ?array
    {
        if ($this->flightId === null) {
            return null;
        }

        return app(LoadDispatch::class)->handle($this->pilot(), $this->flightId);
    }

    /**
     * The route map: the narrowest map that holds the departure and arrival
     * airports, cropped to them (see RouteMaps).
     */
    #[Computed]
    public function routeMap(): ?MapView
    {
        $flight = $this->dispatchPayload['flight'] ?? null;

        if ($flight === null || ($flight->departure === null && $flight->arrival === null)) {
            return null;
        }

        $countries = Airport::query()
            ->whereIn('id', array_filter([$flight->departure?->id, $flight->arrival?->id]))
            ->pluck('country', 'id');

        $point = fn (?AirportPointData $airport): array => [
            'country' => $airport instanceof AirportPointData ? $countries[$airport->id] ?? null : (null),
            'lat'     => $airport?->lat,
            'lon'     => $airport?->lon,
            'label'   => $airport?->icao,
        ];

        return app(RouteMaps::class)->view($point($flight->departure), $point($flight->arrival));
    }

    public function render(): View
    {
        return view('livewire.flights.flight-overview-drawer');
    }

    private function resetState(): void
    {
        $this->reset(['flightId', 'state', 'failureMessage']);
        unset($this->dispatchPayload, $this->routeMap);
    }

    private function pilot(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
