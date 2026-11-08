<?php

declare(strict_types=1);

namespace App\Features\Bids\Livewire;

use App\Features\Bids\Actions\RemoveBid;
use App\Features\Bids\Queries\PilotBids;
use App\Http\Data\BidRowData;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Throwable;

/**
 * My bids: tour legs grouped under their tour, then the plain bids, expiring
 * first. Removing a bid (or cancelling a tour) re-renders the list in place,
 * with no page reload.
 *
 * @property-read list<BidRowData> $rows
 */
class BidList extends Component
{
    /**
     * Shown when a bid could not be removed.
     */
    public ?string $removeError = null;

    /**
     * Remove the pilot's bid on a flight. A tour leg ends the whole tour run.
     */
    public function removeBid(string $flightId): void
    {
        $this->removeError = null;

        try {
            app(RemoveBid::class)->handle($this->pilot(), $flightId);
        } catch (ModelNotFoundException) {
            $this->removeError = 'The bid could not be removed. Try again.';
        } catch (Throwable $exception) {
            report($exception);
            $this->removeError = 'The bid could not be removed. Try again.';
        }

        unset($this->rows);
    }

    /**
     * @return list<BidRowData>
     */
    #[Computed]
    public function rows(): array
    {
        return app(PilotBids::class)->for($this->pilot());
    }

    /**
     * Tour legs by tour, in the order the bids arrived, each tour's legs in flight order.
     *
     * @return list<array{id: int, name: string, rows: list<BidRowData>}>
     */
    #[Computed]
    public function tours(): array
    {
        $groups = [];

        foreach ($this->rows as $row) {
            if ($row->tourId === null || !$row->tourName) {
                continue;
            }

            $groups[$row->tourId] ??= ['id' => $row->tourId, 'name' => $row->tourName, 'rows' => []];
            $groups[$row->tourId]['rows'][] = $row;
        }

        foreach ($groups as &$group) {
            usort($group['rows'], fn (BidRowData $a, BidRowData $b): int => ($a->tourLeg ?? 0) <=> ($b->tourLeg ?? 0));
        }

        return array_values($groups);
    }

    /**
     * @return list<BidRowData>
     */
    #[Computed]
    public function looseBids(): array
    {
        return array_values(array_filter($this->rows, fn (BidRowData $row): bool => $row->tourId === null));
    }

    public function render(): View
    {
        return view('livewire.bids.bid-list');
    }

    private function pilot(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
