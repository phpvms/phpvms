<?php

declare(strict_types=1);

namespace App\Features\Flights\Livewire;

use App\Features\Flights\Queries\SearchFlights;
use App\Http\Data\FlightDispatchPolicyData;
use App\Http\Data\FlightListItemData;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The flight schedule: 17 filters kept in the query string, the manifest rows
 * with a selected-flight panel, and a pager.
 *
 * Filters apply on submit only; the form fields are not live. The query-string
 * names match skylight's (`airline_id`, `dgt`, ...), so a shared link opens the
 * same schedule in either theme.
 *
 * @property-read array{flights: list<FlightListItemData>, policy: FlightDispatchPolicyData, page: array{current: int, last: int, total: int}} $results
 */
class FlightManifest extends Component
{
    /**
     * Property => query-string name.
     *
     * @var array<string, string>
     */
    public const array FILTERS = [
        'airlineId'           => 'airline_id',
        'flightType'          => 'flight_type',
        'flightNumber'        => 'flight_number',
        'routeCode'           => 'route_code',
        'depIcao'             => 'dep_icao',
        'arrIcao'             => 'arr_icao',
        'subfleetId'          => 'subfleet_id',
        'typeRatingId'        => 'type_rating_id',
        'icaoType'            => 'icao_type',
        'search'              => 'search',
        'distanceGreaterThan' => 'dgt',
        'distanceLessThan'    => 'dlt',
        'timeGreaterThan'     => 'tgt',
        'timeLessThan'        => 'tlt',
        'orderBy'             => 'orderBy',
        'sortedBy'            => 'sortedBy',
        'limit'               => 'limit',
    ];

    /**
     * The label each active filter chip carries.
     *
     * @var array<string, string>
     */
    public const array LABELS = [
        'airlineId'           => 'Airline',
        'flightNumber'        => 'Flight',
        'flightType'          => 'Type',
        'routeCode'           => 'Route code',
        'depIcao'             => 'Departure',
        'arrIcao'             => 'Arrival',
        'distanceGreaterThan' => 'Min distance',
        'distanceLessThan'    => 'Max distance',
        'timeGreaterThan'     => 'Min time',
        'timeLessThan'        => 'Max time',
        'subfleetId'          => 'Subfleet',
        'typeRatingId'        => 'Type rating',
        'icaoType'            => 'ICAO type',
        'search'              => 'Search',
        'orderBy'             => 'Sort',
        'sortedBy'            => 'Direction',
        'limit'               => 'Page size',
    ];

    #[Url(as: 'airline_id')]
    public ?string $airlineId = null;

    #[Url(as: 'flight_type')]
    public ?string $flightType = null;

    #[Url(as: 'flight_number')]
    public ?string $flightNumber = null;

    #[Url(as: 'route_code')]
    public ?string $routeCode = null;

    #[Url(as: 'dep_icao')]
    public ?string $depIcao = null;

    #[Url(as: 'arr_icao')]
    public ?string $arrIcao = null;

    #[Url(as: 'subfleet_id')]
    public ?string $subfleetId = null;

    #[Url(as: 'type_rating_id')]
    public ?string $typeRatingId = null;

    #[Url(as: 'icao_type')]
    public ?string $icaoType = null;

    #[Url(as: 'search')]
    public ?string $search = null;

    #[Url(as: 'dgt')]
    public ?string $distanceGreaterThan = null;

    #[Url(as: 'dlt')]
    public ?string $distanceLessThan = null;

    #[Url(as: 'tgt')]
    public ?string $timeGreaterThan = null;

    #[Url(as: 'tlt')]
    public ?string $timeLessThan = null;

    #[Url(as: 'orderBy')]
    public ?string $orderBy = null;

    #[Url(as: 'sortedBy')]
    public ?string $sortedBy = null;

    #[Url(as: 'limit')]
    public ?string $limit = null;

    #[Url(as: 'page')]
    public int $page = 1;

    /**
     * Shown when a filter value is refused; the rest of the schedule still loads.
     */
    public ?string $searchError = null;

    /**
     * Apply the filters in the form: blank fields become unset, and the
     * schedule goes back to its first page.
     */
    public function apply(): void
    {
        foreach (array_keys(self::FILTERS) as $property) {
            $value = is_string($this->{$property}) ? trim($this->{$property}) : $this->{$property};
            $this->{$property} = $value === '' ? null : $value;
        }

        $this->page = 1;
        $this->searchError = $this->searches()->sanitize($this->filters())['invalid'] === []
            ? null
            : 'The schedule could not be updated. The current results are still available.';
    }

    /**
     * Clear every filter.
     */
    public function clear(): void
    {
        $this->reset(array_keys(self::FILTERS));
        $this->page = 1;
        $this->searchError = null;
    }

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function nextPage(): void
    {
        $this->page = min($this->page + 1, max(1, $this->results['page']['last']));

        // The page counts above were read for the old page.
        unset($this->results);
    }

    /**
     * A bid placed or removed in the assignment drawer changes what the rows
     * offer, so re-render them.
     */
    #[On('assignment-drawer.closed')]
    public function drawerClosed(): void
    {
        unset($this->results);
    }

    /**
     * The current page of flights, the dispatch policy and the page counts.
     *
     * @return array{flights: list<FlightListItemData>, policy: FlightDispatchPolicyData, page: array{current: int, last: int, total: int}}
     */
    #[Computed]
    public function results(): array
    {
        return $this->searches()->handle($this->pilot(), $this->searches()->sanitize($this->filters())['valid'], $this->page);
    }

    /**
     * The choices behind the filter selects.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function options(): array
    {
        return $this->searches()->options($this->pilot());
    }

    /**
     * The active filters as chip text, "Label: value".
     *
     * @return list<string>
     */
    #[Computed]
    public function activeFilters(): array
    {
        $chips = [];

        foreach (self::LABELS as $property => $label) {
            $value = $this->{$property};

            if ($value !== null && $value !== '') {
                $chips[] = $label.': '.$value;
            }
        }

        return $chips;
    }

    public function mount(Request $request): void
    {
        // The old URLs name the airports `dpt_airport_id` / `arr_airport_id`.
        $this->depIcao ??= $request->query('dpt_airport_id');
        $this->arrIcao ??= $request->query('arr_airport_id');
    }

    public function render(): View
    {
        return view('livewire.flights.flight-manifest');
    }

    /**
     * @return array<string, ?string>
     */
    private function filters(): array
    {
        $filters = [];

        foreach (self::FILTERS as $property => $queryName) {
            $filters[$queryName] = $this->{$property};
        }

        return $filters;
    }

    private function searches(): SearchFlights
    {
        return app(SearchFlights::class);
    }

    private function pilot(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
