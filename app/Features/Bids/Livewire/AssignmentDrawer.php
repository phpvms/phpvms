<?php

declare(strict_types=1);

namespace App\Features\Bids\Livewire;

use App\Exceptions\BidExistsForAircraft;
use App\Exceptions\BidExistsForFlight;
use App\Exceptions\UserBidLimit;
use App\Features\Bids\Actions\LoadDispatch;
use App\Features\Bids\Actions\PlaceBid;
use App\Features\Bids\Queries\EligibleAircraft;
use App\Features\Flights\Queries\VisibleFlights;
use App\Features\Hooks\Exceptions\HookDenied;
use App\Http\Data\BidSelectionData;
use App\Http\Data\EligibleAircraftData;
use App\Http\Data\EligibleSubfleetData;
use App\Http\Data\FlightDetailData;
use App\Http\Data\FlightDispatchPolicyData;
use App\Models\Aircraft;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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
 * States: idle, selecting (subfleet, then aircraft), overview (a bid exists), error.
 *
 * @property-read array{flight: FlightDetailData, policy: FlightDispatchPolicyData, subfleets: Collection<int, EligibleSubfleetData>, selection: ?BidSelectionData}|null $dispatchPayload
 * @property-read list<EligibleAircraftData>|null $aircraftList
 */
class AssignmentDrawer extends Component
{
    public bool $open = false;

    #[Locked]
    public ?string $flightId = null;

    public string $state = 'idle';

    public ?int $subfleetId = null;

    public ?int $aircraftId = null;

    public bool $editingSubfleet = true;

    public bool $editingAircraft = true;

    /**
     * The last failure's type (network, validation, pilot-limit, flight-conflict,
     * aircraft-conflict, stale-aircraft, hook-denied) and its message.
     */
    public ?string $failureType = null;

    public ?string $failureMessage = null;

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

    public function selectSubfleet(int $subfleetId): void
    {
        if ($this->state !== 'selecting' || $subfleetId === $this->subfleetId) {
            $this->editingSubfleet = false;

            return;
        }

        $configured = collect($this->dispatchPayload['subfleets'] ?? [])->firstWhere('id', $subfleetId);

        if ($configured === null) {
            return;
        }

        $this->subfleetId = $subfleetId;
        $this->aircraftId = null;
        $this->editingSubfleet = false;
        $this->editingAircraft = true;
        unset($this->aircraftList);
    }

    public function editSubfleet(): void
    {
        $this->editingSubfleet = true;
    }

    public function selectAircraft(int $aircraftId): void
    {
        $this->aircraftId = $aircraftId;
        $this->editingAircraft = false;
    }

    public function editAircraft(): void
    {
        $this->editingAircraft = true;
        unset($this->aircraftList);
    }

    /**
     * Run the aircraft list again after it failed to load.
     */
    public function retryAircraft(): void
    {
        unset($this->aircraftList);
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

        if ($payload['policy']->aircraftRequired && $this->aircraftId === null) {
            $this->fail('validation', 'Select an aircraft before placing this bid.');

            return;
        }

        $user = $this->pilot();
        $flight = app(VisibleFlights::class)->for($user)->find($this->flightId);

        if (!$flight instanceof Flight) {
            $this->fail('network', 'The bid could not be placed. Try again.');

            return;
        }

        $aircraft = $this->aircraftId === null ? null : Aircraft::query()->find($this->aircraftId);

        if ($this->aircraftId !== null && !$aircraft instanceof Aircraft) {
            $this->staleAircraft('This aircraft is no longer available. Refresh the aircraft list.');

            return;
        }

        $firstBid = $payload['selection'] === null;

        try {
            app(PlaceBid::class)->handle($flight, $user, $aircraft);
        } catch (HookDenied $exception) {
            $this->fail('hook-denied', $exception->getMessage());

            return;
        } catch (UserBidLimit $exception) {
            $this->fail('pilot-limit', $exception->getMessage());

            return;
        } catch (BidExistsForFlight $exception) {
            $this->fail('flight-conflict', $exception->getMessage());

            return;
        } catch (BidExistsForAircraft $exception) {
            $this->staleAircraft($exception->getMessage(), 'aircraft-conflict');

            return;
        } catch (ValidationException $exception) {
            $message = (string) collect($exception->errors())->flatten()->first();

            if ($this->aircraftId !== null && isset($exception->errors()['aircraftId'])) {
                $this->staleAircraft($message);
            } else {
                $this->fail('validation', $message);
            }

            return;
        }

        $this->failureType = null;
        $this->failureMessage = null;
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

    /**
     * Eligible aircraft for the chosen subfleet; null when they could not be loaded.
     *
     * @return list<EligibleAircraftData>|null
     */
    #[Computed]
    public function aircraftList(): ?array
    {
        if ($this->flightId === null || $this->subfleetId === null) {
            return [];
        }

        try {
            $user = $this->pilot();
            $flight = app(VisibleFlights::class)->for($user)->findOrFail($this->flightId);

            if (!$flight->configuredSubfleets(['airline'])->contains('id', $this->subfleetId)) {
                return null;
            }

            return app(EligibleAircraft::class)->for($flight, $user, $this->subfleetId)
                ->get()
                ->map(fn (Aircraft $aircraft): EligibleAircraftData => EligibleAircraftData::fromModel($aircraft))
                ->values()
                ->all();
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.bids.assignment-drawer');
    }

    /**
     * An aircraft the server no longer accepts: refetch the lists and clear the choice.
     */
    private function staleAircraft(string $message, string $type = 'stale-aircraft'): void
    {
        $this->fail($type, $message);
        $this->subfleetId = null;
        $this->aircraftId = null;
        $this->editingSubfleet = true;
        $this->editingAircraft = true;
        unset($this->dispatchPayload, $this->aircraftList);
    }

    private function fail(string $type, string $message): void
    {
        $this->failureType = $type;
        $this->failureMessage = $message;
    }

    private function resetState(): void
    {
        $this->reset(['flightId', 'state', 'subfleetId', 'aircraftId', 'editingSubfleet', 'editingAircraft', 'failureType', 'failureMessage', 'changed']);
        unset($this->dispatchPayload, $this->aircraftList);
    }

    private function pilot(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
