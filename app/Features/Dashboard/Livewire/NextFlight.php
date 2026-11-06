<?php

declare(strict_types=1);

namespace App\Features\Dashboard\Livewire;

use App\Features\Bids\Queries\PilotBids;
use App\Features\Flights\Queries\FlightDetail;
use App\Http\Data\BidRowData;
use App\Http\Data\FlightDetailData;
use App\Http\Data\FlightDispatchPolicyData;
use App\Models\Airport;
use App\Models\User;
use App\Support\Units\Distance;
use App\Support\Units\Time;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
 *
 * @property-read BidRowData|null $bid
 * @property-read array{flight: FlightDetailData, policy: FlightDispatchPolicyData}|null $detail
 * @property-read bool $canGenerateOfp
 * @property-read string|null $ofpPlanningUrl
 */
class NextFlight extends Component
{
    /**
     * A bid placed or removed in the assignment drawer changes the first bid.
     */
    #[On('assignment-drawer.closed')]
    public function drawerClosed(): void
    {
        unset($this->bid, $this->detail, $this->lastFlight);
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
     * The bid's flight as the flight page shows it (the detail DTO and the dispatch policy),
     * for the `flights.detail.actions` outlet. Null when there is no bid or the flight is no
     * longer visible to the pilot.
     *
     * @return array{flight: FlightDetailData, policy: FlightDispatchPolicyData}|null
     */
    #[Computed]
    public function detail(): ?array
    {
        $bid = $this->bid;

        if ($bid === null) {
            return null;
        }

        try {
            $detail = app(FlightDetail::class)->handle($this->pilot(), $bid->flight->id);
        } catch (ModelNotFoundException) {
            return null;
        }

        return ['flight' => $detail['flight'], 'policy' => $detail['policy']];
    }

    /**
     * Whether the board offers Generate OFP: the bid has no OFP yet, SimBrief is enabled, and the
     * bid satisfies the only-bids rule (the board's flight always has the pilot's bid).
     */
    #[Computed]
    public function canGenerateOfp(): bool
    {
        $bid = $this->bid;
        $policy = $this->detail['policy'] ?? null;

        return $bid !== null
            && $policy !== null
            && !filled($bid->ofpUrl)
            && $policy->simbriefEnabled;
    }

    /**
     * The planning page for the bid, by the bid so it carries the aircraft and opens on the form.
     */
    #[Computed]
    public function ofpPlanningUrl(): ?string
    {
        $bid = $this->bid;

        return $bid === null ? null : route('frontend.ofp.planning', ['bid_id' => $bid->bid->id]);
    }

    /**
     * The pilot's latest flight, read from the `last_pirep` relation the
     * dashboard uses: route, flight time ("1h 10m") and distance ("187 nm").
     * Null when there is none, or when a bid is the next flight.
     *
     * @return array{dpt: string, arr: string, route: string, time: string, distance: string}|null
     */
    #[Computed]
    public function lastFlight(): ?array
    {
        if ($this->bid !== null) {
            return null;
        }

        $pirep = $this->pilot()->last_pirep;

        if ($pirep === null || blank($pirep->dpt_airport_id) || blank($pirep->arr_airport_id)) {
            return null;
        }

        $distance = $pirep->distance;

        return [
            'dpt'      => $pirep->dpt_airport_id,
            'arr'      => $pirep->arr_airport_id,
            'route'    => $pirep->dpt_airport_id.' → '.$pirep->arr_airport_id,
            'time'     => Time::minutesToTimeString((int) $pirep->flight_time),
            'distance' => number_format($distance instanceof Distance ? (float) $distance->toUnit('nmi', 0) : 0).' nm',
        ];
    }

    /**
     * Where the pilot is: the current airport, else the home airport, else
     * null. One query for both ids; a current airport that no longer exists
     * falls through to home.
     */
    #[Computed]
    public function location(): ?Airport
    {
        $pilot = $this->pilot();
        $ids = array_values(array_filter([$pilot->curr_airport_id, $pilot->home_airport_id], filled(...)));

        if ($ids === []) {
            return null;
        }

        $airports = Airport::query()->whereIn('id', $ids)->get()->keyBy('id');

        foreach ($ids as $id) {
            if ($airports->has($id)) {
                return $airports->get($id);
            }
        }

        return null;
    }

    /**
     * The Ready state map options from the theme (`ready_map_scope`,
     * `ready_map_style`, `ready_map_size`). A value outside the allowed list
     * falls back to its default.
     *
     * @return array{scope: string, variant: string, size: string}
     */
    #[Computed]
    public function mapOptions(): array
    {
        /** @param list<string> $allowed */
        $pick = static function (string $option, array $allowed, string $default): string {
            $value = theme_option($option);

            return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
        };

        return [
            'scope'   => $pick('ready_map_scope', ['country', 'continent', 'world'], 'country'),
            'variant' => $pick('ready_map_style', ['outline', 'dotted'], 'dotted'),
            'size'    => $pick('ready_map_size', ['compact', 'hero'], 'compact'),
        ];
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
