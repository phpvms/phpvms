<?php

declare(strict_types=1);

namespace App\Features\Bids\Livewire;

use App\Features\Bids\Actions\LoadDispatch;
use App\Features\Bids\Concerns\SelectsAircraft;
use App\Http\Data\BidSelectionData;
use App\Http\Data\EligibleAircraftData;
use App\Http\Data\EligibleSubfleetData;
use App\Http\Data\FlightDetailData;
use App\Http\Data\FlightDispatchPolicyData;
use App\Models\Flight;
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
 * The flight assignment (bid) drawer: one instance per page.
 *
 * Open it with the Livewire event `assignment-drawer.open` and a `flightId`.
 * It dispatches `assignment-drawer.closed` with `{ flightId, changed }` when it
 * closes. It calls the same actions as the JSON endpoints (LoadDispatch,
 * EligibleAircraft, PlaceBid) and reproduces every error those return, with the
 * same type names and copy.
 *
 * States: idle, selecting (subfleet, then aircraft), overview (a bid exists),
 * error. The flight's details live in the flight overview drawer
 * (App\Features\Flights\Livewire\FlightOverviewDrawer), which hands over here.
 *
 * @property-read array{flight: FlightDetailData, policy: FlightDispatchPolicyData, subfleets: Collection<int, EligibleSubfleetData>, selection: ?BidSelectionData}|null $dispatchPayload
 * @property-read list<EligibleAircraftData>|null $aircraftList
 */
class AssignmentDrawer extends Component
{
    use SelectsAircraft;

    public bool $open = false;

    #[Locked]
    public ?string $flightId = null;

    public string $state = 'idle';

    /**
     * Whether a bid was placed while the drawer was open, reported when it closes.
     */
    #[Locked]
    public bool $changed = false;

    #[On('assignment-drawer.open')]
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
        $this->failureType = null;
        $this->failureMessage = null;
        unset($this->dispatchPayload, $this->aircraftList);

        try {
            $payload = $this->dispatchPayload();
        } catch (ModelNotFoundException) {
            $payload = null;
        }

        if ($payload === null) {
            $this->state = 'error';
            $this->fail('network', 'The flight could not be loaded. Check your connection and try again.');
            $this->dispatch('assignment-drawer.loaded');

            return;
        }

        $this->aircraftId = $payload['selection']?->aircraft?->id;
        $this->subfleetId = null;
        $this->editingSubfleet = true;
        $this->editingAircraft = true;
        $this->state = $payload['selection'] ? 'overview' : 'selecting';

        $this->dispatch('assignment-drawer.loaded');
    }

    public function close(): void
    {
        if ($this->flightId === null) {
            return;
        }

        $closedFlightId = $this->flightId;
        $changed = $this->changed;

        $this->resetState();
        $this->open = false;
        $this->dispatch('assignment-drawer.closed', flightId: $closedFlightId, changed: $changed);
    }

    public function submit(): void
    {
        if ($this->state !== 'selecting' || $this->flightId === null) {
            return;
        }

        $payload = $this->dispatchPayload;

        if ($payload === null) {
            return;
        }

        $firstBid = $payload['selection'] === null;

        if (!$this->placeSelectedBid($payload['policy'])) {
            return;
        }

        $this->changed = true;
        $this->state = 'overview';
        unset($this->dispatchPayload);

        $planningUrl = $this->dispatchPayload['selection']?->ofpPlanningUrl;

        if ($firstBid && $planningUrl) {
            $this->redirect($planningUrl, navigate: true);
        }
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

    public function render(): View
    {
        return view('livewire.bids.assignment-drawer');
    }

    protected function selectionFlightId(): ?string
    {
        return $this->flightId;
    }

    protected function selectionOpen(): bool
    {
        return $this->state === 'selecting';
    }

    protected function selectionSubfleets(): Collection
    {
        return $this->dispatchPayload['subfleets'] ?? collect();
    }

    protected function refreshSelection(): void
    {
        unset($this->dispatchPayload);
    }

    private function resetState(): void
    {
        $this->reset(['flightId', 'state', 'subfleetId', 'aircraftId', 'editingSubfleet', 'editingAircraft', 'failureType', 'failureMessage', 'changed']);
        unset($this->dispatchPayload, $this->aircraftList);
    }

    protected function pilot(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
