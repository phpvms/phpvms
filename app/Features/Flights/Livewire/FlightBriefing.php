<?php

declare(strict_types=1);

namespace App\Features\Flights\Livewire;

use App\Features\Bids\Actions\LoadDispatch;
use App\Features\Bids\Concerns\SelectsAircraft;
use App\Features\Flights\Actions\BuildFlightBriefing;
use App\Features\Flights\Queries\FlightDetail;
use App\Http\Data\EligibleSubfleetData;
use App\Http\Data\FlightBriefingData;
use App\Http\Data\FlightDetailData;
use App\Http\Data\FlightDispatchPolicyData;
use App\Models\Bid;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The flight page's briefing: the route map, the timeline of stations, route
 * and fixes, and the "Before you fly" rail.
 *
 * The flight is loaded by the `FlightDetail` query, as `FlightController::show()`
 * loads it (visible to the pilot, with soft-deleted airports). The briefing only ever uses the signed-in
 * pilot's own OFP. It re-renders when the assignment drawer closes, so the rail
 * follows a bid or aircraft chosen from the page.
 *
 * @property-read array{flight: FlightDetailData, policy: FlightDispatchPolicyData, model: Flight, bid: ?Bid, subfleet: ?string} $detail
 * @property-read array{subfleets: Collection<int, EligibleSubfleetData>}|null $dispatchPayload
 * @property-read string $ofpPlanningUrl
 * @property-read FlightBriefingData                                                            $briefing
 */
class FlightBriefing extends Component
{
    use SelectsAircraft;

    #[Locked]
    public string $flightId;

    public function mount(string $flightId): void
    {
        $this->flightId = $flightId;
    }

    #[On('assignment-drawer.closed')]
    public function drawerClosed(): void
    {
        unset($this->detail);
    }

    /**
     * @return array{flight: FlightDetailData, policy: FlightDispatchPolicyData, model: Flight, bid: ?Bid, subfleet: ?string}
     */
    #[Computed]
    public function detail(): array
    {
        return app(FlightDetail::class)->handle($this->pilot(), $this->flightId);
    }

    /**
     * The flight's subfleets with their eligible-aircraft counts, from LoadDispatch.
     * Only read while an aircraft may be chosen.
     *
     * @return array{subfleets: Collection<int, EligibleSubfleetData>}
     */
    #[Computed]
    public function dispatchPayload(): array
    {
        return app(LoadDispatch::class)->handle($this->pilot(), $this->flightId);
    }

    /**
     * Place the bid with the chosen aircraft, through the same flow as the assignment drawer.
     */
    public function placeBid(): void
    {
        if (!$this->selectionOpen() || !$this->placeSelectedBid($this->detail['policy'])) {
            return;
        }

        $this->reset(['subfleetId', 'aircraftId', 'editingSubfleet', 'editingAircraft']);
        $this->refreshSelection();
        unset($this->detail, $this->aircraftList);
    }

    /**
     * The planning page for this flight. With a bid it goes by the bid, which carries the aircraft, so
     * the pilot lands on the form; without one the page asks for the aircraft itself.
     */
    #[Computed]
    public function ofpPlanningUrl(): string
    {
        $bid = $this->detail['bid'];

        return $bid instanceof Bid
            ? route('frontend.ofp.planning', ['bid_id' => $bid->id])
            : $this->detail['flight']->ofpPlanningUrl;
    }

    #[Computed]
    public function briefing(): FlightBriefingData
    {
        return app(BuildFlightBriefing::class)->handle($this->detail['model'], $this->pilot());
    }

    public function render(): View
    {
        return view('livewire.flights.flight-briefing');
    }

    protected function pilot(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    protected function selectionFlightId(): ?string
    {
        return $this->flightId;
    }

    protected function selectionOpen(): bool
    {
        return $this->detail['bid'] === null && $this->detail['flight']->summary->primaryAction === 'bid';
    }

    /**
     * @return Collection<int, EligibleSubfleetData>
     */
    protected function selectionSubfleets(): Collection
    {
        return $this->dispatchPayload['subfleets'];
    }

    protected function refreshSelection(): void
    {
        unset($this->dispatchPayload);
    }
}
