<div class="row">
  <div class="col">
    <table class="table table-sm table-borderless align-middle text-nowrap mb-2">
      <tr>
        <th>@sortablelink('airline_id', __('common.airline'))</th>
        <th>@sortablelink('flight_number', __('flights.flightnumber'))</th>
        <th>@sortablelink('dpt_airport_id', __('airports.departure'))</th>
        <th>@sortablelink('arr_airport_id', __('airports.arrival'))</th>
        <th>@sortablelink('dpt_time', 'STD')</th>
        <th>@sortablelink('arr_time', 'STA')</th>
        <th>@sortablelink('distance', 'Distance')</th>
        <th>@sortablelink('flight_time', 'Flight Time')</th>
      </tr>
    </table>
  </div>
</div>
@foreach($flights as $flight)
  <div class="card mb-3">
    <div class="card-body">
      <div class="row">
        <div class="col-sm-12">
          <div class="flex-row justify-content-between d-flex">
            <div class="d-flex flex-row center-align" style="font-size: 1.4rem; line-height: 1.4rem; font-weight: 600; text-align: center">
              @if(optional($flight->airline)->logo)
                  <img src="{{ $flight->airline->logo }}" alt="{{$flight->airline->name}}"
                    style="max-width: 80px; width: 100%; height: auto;"/>
              @else
                {{ $flight->airline->name }}: 
              @endif
              <span class="ms-1">
              @if($flight->airline->iata)
              {{ $flight->airline->icao }}{{$flight->flight_number}} |
              @endif
              {{ $flight->ident }}
              @if(filled($flight->callsign) && !setting('simbrief.callsign', true))
                {{ '| '. $flight->atc }}
              @endif
              </span>
              </div>
              <div><span class="badge bg-secondary">{{$flight->flight_type}}&nbsp;<span class="d-none d-sm-inline">({{\App\Models\Enums\FlightType::label($flight->flight_type)}})</span></span></div>
          </div>
          <div class="my-2 d-flex flex-row justify-content-between">
            <div class="d-flex flex-column text-start">
              <div class="fs-2" style="font-weight: 600">
                <a href="{{ route('frontend.airports.show', [$flight->dpt_airport_id]) }}">
                  {{ $flight->dpt_airport_id }}
                </a>
              </div>
              <div class="fs-5 d-none d-md-flex">{{$flight->dpt_airport->name}}</div>
              <div class="fs-5">
                {{$flight->dpt_time}}
              </div>
            </div>
            <div class="d-flex flex-column text-end">
              <div class="fs-2" style="font-weight: 600">
                <a href="{{ route('frontend.airports.show', [$flight->arr_airport_id]) }}">
                  {{$flight->arr_airport_id}}
                </a>
              </div>
              <div class="fs-5 d-none d-md-flex">{{$flight->arr_airport->name}}</div>
              <div class="fs-5">
                {{$flight->arr_time}}
              </div>
            </div>
          </div>
          <div class="d-flex flex-row justify-content-between">
            <div class="text-center fs-5">
              @if($flight->flight_time)@minutestotime($flight->flight_time)@endif{{$flight->flight_time && $flight->distance ? '/' : ''}}{{$flight->distance ? $flight->distance.'nm' : ''}}
            </div>
            <div class="fs-5">
                @if(count($flight->subfleets) !== 0)
                @php
                  $arr = [];
                  foreach ($flight->subfleets as $sf) {
                    $arr[] = "{$sf->type}";
                  }
                  $display = count($arr) > 2 ? implode(", ", array_slice($arr, 0, 2)) . '...' : implode(", ", $arr);
                  $allSubfleets = implode(", ", $arr);
                @endphp
                <span data-bs-toggle="popover" data-bs-trigger="hover" data-bs-placement="bottom" data-bs-content="{{ $allSubfleets }}">
                  {{ $display }}
                </span>
                @else
                Any Subfleet
                @endif
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="card-footer">
      <a class="btn btn-sm btn-primary" href="{{ route('frontend.flights.show', [$flight->id]) }}">
        {{ __('flights.viewflight') }}
      </a>
      @if ($acars_plugin)
            @if (isset($saved[$flight->id]))
              <a href="vmsacars:bid/{{ $saved[$flight->id] }}" class="btn btn-sm btn-primary">Load in vmsACARS</a>
            @else
              <a href="vmsacars:flight/{{ $flight->id }}" class="btn btn-sm btn-primary">Load in vmsACARS</a>
            @endif
          @endif
          @if ($simbrief !== false)
            @if ($flight->simbrief && $flight->simbrief->user_id === $user->id)
              <a href="{{ route('frontend.simbrief.briefing', $flight->simbrief->id) }}"
                 class="btn btn-sm btn-primary">
                View Simbrief Flight Plan
              </a>
            @else
              @if ($simbrief_bids === false || ($simbrief_bids === true && isset($saved[$flight->id])))
                @php
                  $aircraft_id = isset($saved[$flight->id]) ? App\Models\Bid::find($saved[$flight->id])->aircraft_id : null;
                @endphp
                <a href="{{ route('frontend.simbrief.generate') }}?flight_id={{ $flight->id }}@if($aircraft_id)&aircraft_id={{ $aircraft_id }} @endif"
                   class="btn btn-sm btn-primary">
                  Create Simbrief Flight Plan
                </a>
              @endif
            @endif
          @endif
      <a href="{{ route('frontend.pireps.create') }}?flight_id={{ $flight->id }}"
        class="btn btn-sm btn-info">
       {{ __('pireps.newpirep') }}
     </a>
      @if (!setting('pilots.only_flights_from_current') || $flight->dpt_airport_id == $user->current_airport->icao)
            <button class="btn btn-sm save_flight
                           {{ isset($saved[$flight->id]) ? 'btn-danger':'btn-success' }}"
                    x-id="{{ $flight->id }}"
                    x-saved-class="btn-danger"
                    type="button"
                    title="@lang('flights.addremovebid')">
                    {{ isset($saved[$flight->id]) ? 'Remove Bid':'Add Bid' }}
            </button>
          @endif
          </div>
  </div>

@endforeach
