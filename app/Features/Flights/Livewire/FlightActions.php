<?php

declare(strict_types=1);

namespace App\Features\Flights\Livewire;

use App\Features\Flights\Queries\FlightDetail;
use App\Http\Data\FlightDetailData;
use App\Http\Data\FlightDispatchPolicyData;
use App\Models\Bid;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The flight page's bid controls: the Bid / Open bid overview / Unavailable
 * button, Generate OFP, and the `flights.detail.actions` outlet.
 *
 * It re-renders when the assignment drawer closes, so the button follows a bid
 * placed from the page.
 */
class FlightActions extends Component
{
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
        /** @var User $user */
        $user = Auth::user();

        return app(FlightDetail::class)->handle($user, $this->flightId);
    }

    public function render(): View
    {
        return view('livewire.flights.flight-actions');
    }
}
