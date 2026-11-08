@props(['options', 'active' => [], 'searchError' => null])

{{--
  The flight manifest's filters: the active-filter chips, the Filters toggle and the filter form.
  Props: options (FlightManifest's airlines, flightTypes, subfleets, typeRatings, icaoTypes), active (chip labels),
  searchError. The fields bind with wire:model to App\Features\Flights\Livewire\FlightManifest, which applies
  them on apply() and resets them on clear().
--}}
<section class="vms-flight-filters px-4 pt-3.5 pb-4" aria-label="Flight filters" x-data="{ open: {{ count($active) > 0 ? 'true' : 'false' }} }">
    <div class="flex flex-wrap items-center gap-2.5">
        <div class="vms-flight-chips flex min-w-0 flex-1 flex-wrap gap-1.5" aria-label="Active filters">
            @foreach ($active as $chip)
                <span wire:key="chip-{{ $loop->index }}" class="vms-flight-chip inline-flex items-center px-3 py-1 text-[11.5px] font-medium">{{ $chip }}</span>
            @endforeach
        </div>
        <x-ts-button type="button" sm round light x-on:click="open = ! open" x-bind:aria-expanded="open ? 'true' : 'false'" aria-controls="flight-filter-form">Filters</x-ts-button>
    </div>

    @if ($searchError)
        <x-ts-alert color="red" light shadowless role="alert" class="mt-3">{{ $searchError }}</x-ts-alert>
    @endif

    <form id="flight-filter-form" wire:submit="apply" x-show="open" x-cloak class="vms-flight-filters-form mt-4 grid grid-cols-1 gap-x-3 gap-y-2.5 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
        <label class="vms-field"><span>Airline</span>
            <select wire:model="airlineId">
                <option value="">All airlines</option>
                @foreach ($options['airlines'] as $value => $label)
                    @continue($value === '')
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="vms-field"><span>Flight type</span>
            <select wire:model="flightType">
                <option value="">All types</option>
                @foreach ($options['flightTypes'] as $value => $label)
                    @continue($value === '')
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="vms-field"><span>Flight number</span>
            <input type="text" wire:model="flightNumber" autocomplete="off" placeholder="104" />
        </label>
        <label class="vms-field"><span>Route code</span>
            <input type="text" wire:model="routeCode" autocomplete="off" placeholder="A" />
        </label>
        <label class="vms-field"><span>Departure</span>
            <input type="text" wire:model="depIcao" autocomplete="off" maxlength="8" placeholder="KDFW" />
        </label>
        <label class="vms-field"><span>Arrival</span>
            <input type="text" wire:model="arrIcao" autocomplete="off" maxlength="8" placeholder="KORD" />
        </label>
        <label class="vms-field"><span>Subfleet</span>
            <select wire:model="subfleetId">
                <option value="">All subfleets</option>
                @foreach ($options['subfleets'] as $value => $label)
                    @continue($value === '')
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="vms-field"><span>Type rating</span>
            <select wire:model="typeRatingId">
                <option value="">All ratings</option>
                @foreach ($options['typeRatings'] as $rating)
                    <option value="{{ $rating['id'] }}">{{ $rating['type'] }} · {{ $rating['name'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="vms-field"><span>ICAO type</span>
            <select wire:model="icaoType">
                <option value="">All aircraft</option>
                @foreach ($options['icaoTypes'] as $type)
                    <option value="{{ $type }}">{{ $type }}</option>
                @endforeach
            </select>
        </label>
        <label class="vms-field sm:col-span-2"><span>Free text</span>
            <input type="text" wire:model="search" autocomplete="off" placeholder="Airport, route, callsign" />
        </label>
        <label class="vms-field"><span>Minimum distance</span>
            <input type="text" wire:model="distanceGreaterThan" inputmode="numeric" placeholder="0" />
        </label>
        <label class="vms-field"><span>Maximum distance</span>
            <input type="text" wire:model="distanceLessThan" inputmode="numeric" placeholder="Any" />
        </label>
        <label class="vms-field"><span>Minimum time</span>
            <input type="text" wire:model="timeGreaterThan" inputmode="numeric" placeholder="Minutes" />
        </label>
        <label class="vms-field"><span>Maximum time</span>
            <input type="text" wire:model="timeLessThan" inputmode="numeric" placeholder="Minutes" />
        </label>
        <label class="vms-field"><span>Sort by</span>
            <select wire:model="orderBy">
                <option value="">Default</option>
                <option value="dpt_time">Departure time</option>
                <option value="arr_time">Arrival time</option>
                <option value="distance">Distance</option>
                <option value="flight_number">Flight number</option>
            </select>
        </label>
        <label class="vms-field"><span>Direction</span>
            <select wire:model="sortedBy">
                <option value="">Default</option>
                <option value="asc">Ascending</option>
                <option value="desc">Descending</option>
            </select>
        </label>
        <label class="vms-field"><span>Page size</span>
            <input type="text" wire:model="limit" inputmode="numeric" placeholder="25" />
        </label>

        <div class="vms-flight-filters-actions col-span-full flex justify-end gap-2">
            <x-ts-button submit sm wire:loading.attr="disabled" wire:target="apply,clear">
                <span wire:loading.remove wire:target="apply">Apply filters</span>
                <span wire:loading wire:target="apply">Updating results</span>
            </x-ts-button>
            <x-ts-button type="button" sm light wire:click="clear" wire:loading.attr="disabled" wire:target="apply,clear">Clear</x-ts-button>
        </div>
    </form>
</section>
