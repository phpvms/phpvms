<?php

declare(strict_types=1);

namespace App\Features\Dashboard\Livewire;

use App\Features\Bids\Queries\PilotBids;
use App\Http\Data\BidRowData;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The next flight widget: the pilot's first bid, with a way into its planning,
 * or a "Ready to fly" state when there is no bid.
 *
 * Reads the same rows as My bids (`PilotBids`), so the first row is the bid
 * that expires next. A bid placed or removed in the assignment drawer
 * re-renders the widget.
 */
class NextFlight extends Component
{
    /**
     * A bid placed or removed in the assignment drawer changes the first bid.
     */
    #[On('assignment-drawer.closed')]
    public function drawerClosed(): void
    {
        unset($this->bid, $this->lastRoute);
    }

    /**
     * The pilot's first bid, or null when there is none.
     */
    #[Computed]
    public function bid(): ?BidRowData
    {
        return app(PilotBids::class)->for($this->pilot())[0] ?? null;
    }

    /**
     * The route of the pilot's latest flight as "DEP → ARR", read from the
     * `last_pirep` relation the dashboard uses. Null when there is none, or when
     * a bid is the next flight.
     */
    #[Computed]
    public function lastRoute(): ?string
    {
        if ($this->bid !== null) {
            return null;
        }

        $pirep = $this->pilot()->last_pirep;

        if ($pirep === null || blank($pirep->dpt_airport_id) || blank($pirep->arr_airport_id)) {
            return null;
        }

        return $pirep->dpt_airport_id.' → '.$pirep->arr_airport_id;
    }

    public function render(): View
    {
        return view('livewire.dashboard.next-flight');
    }

    private function pilot(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
