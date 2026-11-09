<?php

declare(strict_types=1);

namespace App\Features\Tour\Livewire;

use App\Enums\BundleType;
use App\Features\Bids\Actions\RemoveBid;
use App\Features\Tour\Enums\TourStatus;
use App\Features\Tour\Models\UserTour;
use App\Http\Data\TourListItemData;
use App\Models\FlightBundle;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * The tours index: one card per tour with the pilot's latest run through it.
 *
 * Bidding opens the assignment drawer (a browser event the card dispatches);
 * the drawer's `assignment-drawer.closed` event re-renders the list. Cancelling
 * a tour removes the active leg's bid, which `RemoveBid` turns into a cancel of
 * the whole run. The All / My tours tabs and the leg window are Alpine.
 *
 * @property-read list<TourListItemData> $tours
 */
class TourList extends Component
{
    /** @var array<int, string> The failure to show on a tour's card, keyed by tour id. */
    public array $cancelErrors = [];

    /**
     * Every enabled tour, each with the pilot's latest run through it. A
     * disabled bundle stays listed while the pilot has a run in progress, and a
     * run in progress comes first.
     *
     * @return list<TourListItemData>
     */
    #[Computed]
    public function tours(): array
    {
        $user = $this->pilot();

        $liveBundleIds = UserTour::query()
            ->where('user_id', $user->id)
            ->where('status', TourStatus::InProgress)
            ->pluck('bundle_id');

        $bundles = FlightBundle::query()
            ->where('type', BundleType::Tour)
            ->where(fn ($query) => $query->where('enabled', true)->orWhereIn('id', $liveBundleIds))
            ->orderBy('name')
            ->get();

        $latestRuns = UserTour::query()
            ->where('user_id', $user->id)
            ->whereIn('bundle_id', $bundles->pluck('id'))
            ->orderByDesc('started_at')
            ->get()
            ->unique('bundle_id')
            ->keyBy('bundle_id');

        [$running, $rest] = $bundles->partition(
            fn (FlightBundle $bundle): bool => $latestRuns->get($bundle->id)?->status === TourStatus::InProgress
        );

        return $running->concat($rest)
            ->map(fn (FlightBundle $bundle): TourListItemData => TourListItemData::fromModel(
                $bundle,
                $latestRuns->get($bundle->id),
            ))
            // A tour whose legs do not run 1..N has nothing to offer; what the
            // pilot already ran is history and stays listed.
            ->filter(fn (TourListItemData $tour): bool => $tour->valid || $tour->status !== null)
            ->values()
            ->all();
    }

    /**
     * The drawer closed: the list re-renders with the pilot's new run state.
     */
    #[On('assignment-drawer.closed')]
    public function refreshTours(): void
    {
        unset($this->tours);
    }

    /**
     * Cancel a tour in progress by dropping the bid on its active leg.
     */
    public function cancelTour(int $tourId): void
    {
        unset($this->cancelErrors[$tourId]);

        $tour = collect($this->tours)->first(fn (TourListItemData $item): bool => $item->id === $tourId);

        if (!$tour instanceof TourListItemData || $tour->status !== TourStatus::InProgress->value || $tour->activeLegFlightId === null) {
            return;
        }

        try {
            app(RemoveBid::class)->handle($this->pilot(), $tour->activeLegFlightId);
        } catch (ValidationException $exception) {
            $this->cancelErrors[$tourId] = collect($exception->errors())->flatten()->first() ?? 'The tour could not be cancelled. Try again.';

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->cancelErrors[$tourId] = 'The tour could not be cancelled. Try again.';

            return;
        }

        unset($this->tours);
    }

    public function render(): View
    {
        return view('livewire.tours.tour-list');
    }

    private function pilot(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
