<?php

declare(strict_types=1);

namespace App\Features\Ofp\Livewire;

use App\Exceptions\AbstractHttpException;
use App\Features\Bids\Actions\LoadDispatch;
use App\Features\Bids\Actions\PlaceBid;
use App\Features\Bids\Queries\EligibleAircraft;
use App\Features\Flights\Queries\VisibleFlights;
use App\Http\Data\EligibleAircraftData;
use App\Http\Data\EligibleSubfleetData;
use App\Models\Aircraft;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Aircraft selection on the OFP planning page, shown while no aircraft is
 * chosen yet.
 *
 * In the bid flow the choice is written to the pilot's bid (the same
 * `PlaceBid` action the bid drawer uses) and planning is revisited with the
 * bid. Otherwise planning is revisited with `?aircraft_id=`.
 *
 * @property-read Collection<int, EligibleSubfleetData> $subfleets
 * @property-read Collection<int, EligibleAircraftData>  $aircraft
 */
class AircraftSelection extends Component
{
    #[Locked]
    public string $flightId = '';

    #[Locked]
    public ?int $bidId = null;

    public ?int $subfleetId = null;

    public ?string $failure = null;

    public function mount(string $flightId, ?int $bidId = null): void
    {
        $this->flightId = $flightId;
        $this->bidId = $bidId;
    }

    /**
     * The flight's configured subfleets with their eligible-aircraft counts.
     *
     * @return Collection<int, EligibleSubfleetData>
     */
    #[Computed]
    public function subfleets(): Collection
    {
        return app(LoadDispatch::class)->handle($this->pilot(), $this->flightId)['subfleets'];
    }

    /**
     * Eligible aircraft of the chosen subfleet.
     *
     * @return Collection<int, EligibleAircraftData>
     */
    #[Computed]
    public function aircraft(): Collection
    {
        if ($this->subfleetId === null) {
            return collect();
        }

        $user = $this->pilot();
        $flight = app(VisibleFlights::class)->for($user)->findOrFail($this->flightId);

        return app(EligibleAircraft::class)->for($flight, $user, $this->subfleetId)
            ->get()
            ->map(fn (Aircraft $aircraft): EligibleAircraftData => EligibleAircraftData::fromModel($aircraft))
            ->values();
    }

    public function selectSubfleet(?int $subfleetId): void
    {
        $this->failure = null;

        if ($subfleetId !== null && !$this->subfleets->contains(fn (EligibleSubfleetData $subfleet): bool => $subfleet->id === $subfleetId && !$subfleet->disabled)) {
            $subfleetId = null;
        }

        $this->subfleetId = $subfleetId;
        unset($this->aircraft);
    }

    public function chooseAircraft(int $aircraftId): void
    {
        $this->failure = null;

        $user = $this->pilot();
        $flight = app(VisibleFlights::class)->for($user)->findOrFail($this->flightId);
        $aircraft = app(EligibleAircraft::class)->for($flight, $user)->whereKey($aircraftId)->first();

        if (!$aircraft instanceof Aircraft) {
            $this->failure = 'This aircraft is no longer eligible. Refresh the aircraft list.';
            unset($this->aircraft);

            return;
        }

        if ($this->bidId === null) {
            $this->redirect(route('frontend.ofp.planning', ['flight_id' => $flight->id, 'aircraft_id' => $aircraft->id]));

            return;
        }

        $bid = Bid::query()->where('user_id', $user->id)->where('flight_id', $flight->id)->findOrFail($this->bidId);

        try {
            app(PlaceBid::class)->handle($flight, $user, $aircraft);
        } catch (AbstractHttpException $exception) {
            $this->failure = $exception->getMessage();

            return;
        } catch (ValidationException $exception) {
            $this->failure = collect($exception->errors())->flatten()->first() ?? 'The aircraft could not be assigned to this bid.';

            return;
        }

        $this->redirect(route('frontend.ofp.planning', ['bid_id' => $bid->id]));
    }

    public function render(): View
    {
        return view('livewire.ofp.aircraft-selection');
    }

    private function pilot(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
