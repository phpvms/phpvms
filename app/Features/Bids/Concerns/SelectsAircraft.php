<?php

declare(strict_types=1);

namespace App\Features\Bids\Concerns;

use App\Exceptions\BidExistsForAircraft;
use App\Exceptions\BidExistsForFlight;
use App\Exceptions\UserBidLimit;
use App\Features\Bids\Actions\PlaceBid;
use App\Features\Bids\Queries\EligibleAircraft;
use App\Features\Flights\Queries\VisibleFlights;
use App\Features\Hooks\Exceptions\HookDenied;
use App\Http\Data\EligibleAircraftData;
use App\Http\Data\EligibleSubfleetData;
use App\Http\Data\FlightDispatchPolicyData;
use App\Models\Aircraft;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

/**
 * The subfleet, aircraft and place-bid flow behind `x-vms::bids.aircraft-selection`,
 * shared by the assignment drawer and the flight briefing's rail.
 *
 * The component supplies the flight, the subfleets and whether a choice may be
 * made now. The bid is only ever placed through `PlaceBid`, so the placing-bid
 * hook, eligibility and the bid limits apply everywhere.
 *
 * @property-read list<EligibleAircraftData>|null $aircraftList
 */
trait SelectsAircraft
{
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

    abstract protected function pilot(): User;

    /**
     * The flight being bid on.
     */
    abstract protected function selectionFlightId(): ?string;

    /**
     * Whether a subfleet or aircraft may be chosen now (a bid is not placed yet, the flight is open).
     */
    abstract protected function selectionOpen(): bool;

    /**
     * The flight's configured subfleets with their eligible-aircraft counts.
     *
     * @return Collection<int, EligibleSubfleetData>
     */
    abstract protected function selectionSubfleets(): Collection;

    /**
     * Drop what the component computed from the flight and the bid, so the next render reads it again.
     */
    abstract protected function refreshSelection(): void;

    public function selectSubfleet(int $subfleetId): void
    {
        if (!$this->selectionOpen() || $subfleetId === $this->subfleetId) {
            $this->editingSubfleet = false;

            return;
        }

        if ($this->selectionSubfleets()->firstWhere('id', $subfleetId) === null) {
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

    /**
     * Eligible aircraft for the chosen subfleet; null when they could not be loaded.
     *
     * @return list<EligibleAircraftData>|null
     */
    #[Computed]
    public function aircraftList(): ?array
    {
        $flightId = $this->selectionFlightId();

        if ($flightId === null || $this->subfleetId === null) {
            return [];
        }

        try {
            $user = $this->pilot();
            $flight = app(VisibleFlights::class)->for($user)->findOrFail($flightId);

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

    /**
     * Place the bid with the chosen aircraft. Returns whether it was placed; a refusal sets the failure.
     */
    protected function placeSelectedBid(FlightDispatchPolicyData $policy): bool
    {
        $flightId = $this->selectionFlightId();

        if ($flightId === null) {
            return false;
        }

        if ($policy->aircraftRequired && $this->aircraftId === null) {
            $this->fail('validation', 'Select an aircraft before placing this bid.');

            return false;
        }

        $user = $this->pilot();
        $flight = app(VisibleFlights::class)->for($user)->find($flightId);

        if (!$flight instanceof Flight) {
            $this->fail('network', 'The bid could not be placed. Try again.');

            return false;
        }

        $aircraft = $this->aircraftId === null ? null : Aircraft::query()->find($this->aircraftId);

        if ($this->aircraftId !== null && !$aircraft instanceof Aircraft) {
            $this->staleAircraft('This aircraft is no longer available. Refresh the aircraft list.');

            return false;
        }

        try {
            app(PlaceBid::class)->handle($flight, $user, $aircraft);
        } catch (HookDenied $exception) {
            $this->fail('hook-denied', $exception->getMessage());

            return false;
        } catch (UserBidLimit $exception) {
            $this->fail('pilot-limit', $exception->getMessage());

            return false;
        } catch (BidExistsForFlight $exception) {
            $this->fail('flight-conflict', $exception->getMessage());

            return false;
        } catch (BidExistsForAircraft $exception) {
            $this->staleAircraft($exception->getMessage(), 'aircraft-conflict');

            return false;
        } catch (ValidationException $exception) {
            $message = (string) collect($exception->errors())->flatten()->first();

            if ($this->aircraftId !== null && isset($exception->errors()['aircraftId'])) {
                $this->staleAircraft($message);
            } else {
                $this->fail('validation', $message);
            }

            return false;
        }

        $this->failureType = null;
        $this->failureMessage = null;

        return true;
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
        $this->refreshSelection();
        unset($this->aircraftList);
    }

    private function fail(string $type, string $message): void
    {
        $this->failureType = $type;
        $this->failureMessage = $message;
    }
}
