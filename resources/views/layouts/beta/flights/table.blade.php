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
  <div class="card border-blue-bottom">
    <div class="card-body" style="min-height: 0">
      <div class="row">
        <div class="col-sm-9">
          <h5>
            <a class="text-c" href="{{ route('frontend.flights.show', [$flight->id]) }}">
              @if(optional($flight->airline)->logo)
                  <img src="{{ $flight->airline->logo }}"  alt="{{$flight->airline->name}}"
                    style="max-width: 80px; width: 100%; height: auto;"/>
              @endif
              {{ $flight->ident }}
              @if(filled($flight->callsign) && !setting('simbrief.callsign', true))
                {{ '| '. $flight->atc }}
              @endif
            </a>
          </h5>
        </div>
        <div class="col-sm-3 align-top text-right">
          {{--
          !!! NOTE !!!
           Don't remove the "save_flight" class, or the x-id attribute.
           It will break the AJAX to save/delete

           "x-saved-class" is the class to add/remove if the bid exists or not
           If you change it, remember to change it in the in-array line as well
          --}}
          @if (!setting('pilots.only_flights_from_current') || $flight->dpt_airport_id == $user->current_airport->icao)
            <button class="btn btn-round btn-icon btn-icon-mini save_flight
                           {{ isset($saved[$flight->id]) ? 'btn-info':'' }}"
                    x-id="{{ $flight->id }}"
                    x-saved-class="btn-info"
                    type="button"
                    title="@lang('flights.addremovebid')">
              <i class="fas fa-map-marker"></i>
            </button>
          @endif
        </div>
      </div>
      <div class="row">
        <div class="col-sm-7">
          {{--<table class="table-condensed"></table>--}}
          <span class="title">{{ strtoupper(__('flights.dep')) }}&nbsp;</span>
          {{ optional($flight->dpt_airport)->name ?? $flight->dpt_airport_id }}
          (<a href="{{route('frontend.airports.show', ['id' => $flight->dpt_airport_id])}}">{{$flight->dpt_airport_id}}</a>)
          @if($flight->dpt_time), {{ $flight->dpt_time }}@endif
          <br/>
          <span class="title">{{ strtoupper(__('flights.arr')) }}&nbsp;</span>
          {{ optional($flight->arr_airport)->name ?? $flight->arr_airport_id }}
          (<a href="{{route('frontend.airports.show', ['id' => $flight->arr_airport_id])}}">{{$flight->arr_airport_id}}</a>)
          @if($flight->arr_time), {{ $flight->arr_time }}@endif
          <br/>
          @if(filled($flight->callsign) && !setting('simbrief.callsign', true))
            <span class="title">{{ strtoupper(__('flights.callsign')) }}&nbsp;</span>
            {{ $flight->atc }}
            <br/>
          @endif
          @if($flight->distance)
            <span class="title">{{ strtoupper(__('common.distance')) }}&nbsp;</span>
            {{ $flight->distance }} {{ setting('units.distance') }}
            <br/>
          @endif
          @if($flight->level)
            <span class="title">{{ strtoupper(__('flights.level')) }}&nbsp;</span>
            {{ $flight->level }} {{ setting('units.altitude') }}
            <br/>
          @endif
        </div>
        <div class="col-sm-5">
          @if($flight->route)
            <span class="title">{{ strtoupper(__('flights.route')) }}&nbsp;</span>
            {{ $flight->route }}
          @endif
        </div>
      </div>
      <div class="row">
        <div class="col-sm-12 text-right">
          @if (!setting('pilots.only_flights_from_current') || $flight->dpt_airport_id === $user->current_airport->icao)
            @if ($acars_plugin)
              @if (isset($saved[$flight->id]))
                <a href="vmsacars:bid/{{ $saved[$flight->id] }}" class="btn btn-sm btn-outline-primary">Load in vmsACARS</a>
              @else
                <a href="vmsacars:flight/{{ $flight->id }}" class="btn btn-sm btn-outline-primary">Load in vmsACARS</a>
              @endif
             @endif
            <!-- Simbrief enabled -->
            @if ($simbrief !== false)
              <!-- If this flight has a briefing, show the link to view it-->
              @if ($flight->simbrief && $flight->simbrief->user_id === $user->id)
                <a href="{{ route('frontend.simbrief.briefing', $flight->simbrief->id) }}"
                   class="btn btn-sm btn-outline-primary">
                  View Simbrief Flight Plan
                </a>
              @else
                <!-- Show button if the bids-only is disable, or if bids-only is enabled, they've saved it -->
                @if ($simbrief_bids === false || ($simbrief_bids === true && isset($saved[$flight->id])))
                  @php
                    $aircraft_id = isset($saved[$flight->id]) ? App\Models\Bid::find($saved[$flight->id])->aircraft_id : null;
                  @endphp
                  <a href="{{ route('frontend.simbrief.generate') }}?flight_id={{ $flight->id }}@if($aircraft_id)&aircraft_id={{ $aircraft_id }} @endif"
                     class="btn btn-sm btn-outline-primary">
                    Create Simbrief Flight Plan
                  </a>
                @endif
              @endif
             @endif
            <a href="{{ route('frontend.pireps.create') }}?flight_id={{ $flight->id }}"
               class="btn btn-sm btn-outline-info">
              {{ __('pireps.newpirep') }}
            </a>
          @endif
        </div>
      </div>
    </div>
  </div>

@endforeach
