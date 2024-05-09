<h4 class="description">@lang('flights.search')</h4>
<div class="row">
  <div class="col-12">
    <div class="form-group search-form">
      <form method="get" action="{{ route('frontend.flights.search') }}">
        @csrf
      <div>
        <div class="form-group">
          <div>@lang('common.airline')</div>
          <select name="airline_id" id="airline_id" class="form-control select2">
            @foreach($airlines as $airline_id => $airline_label)
              <option value="{{ $airline_id }}" @if(request()->get('airline_id') == $airline_id) selected @endif>{{ $airline_label }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="mt-3">
        <div>@lang('flights.flighttype')</div>
        <select name="flight_type" id="flight_type" class="form-control select2">
          @foreach($flight_types as $flight_type_id => $flight_type_label)
            <option value="{{ $flight_type_id }}" @if(request()->get('flight_type') == $flight_type_id) selected @endif>{{ $flight_type_label }}</option>
          @endforeach
        </select>
      </div>

      <div class="mt-3">
        <div>@lang('flights.flightnumber')</div>
        <input type="text" name="flight_number" id="flight_number" class="form-control" value="{{ request()->get('flight_number') }}" />
      </div>

      <div class="mt-3">
        <div>@lang('flights.code')</div>
        <input type="text" name="route_code" id="route_code" class="form-control" value="{{ request()->get('route_code') }}" />
      </div>

      <div class="mt-3">
        <div>@lang('airports.departure')</div>
        <select name="dep_icao" id="dep_icao" class="form-control airport_search">
        </select>
      </div>

      <div class="mt-3">
        <div>@lang('airports.arrival')</div>
        <select name="arr_icao" id="arr_icao" class="form-control airport_search">
        </select>
      </div>

      <div class="mt-3">
        <div>@lang('common.subfleet')</div>
        <select name="subfleet_id" id="subfleet_id" class="form-control select2">
          @foreach($subfleets as $subfleet_id => $subfleet_label)
            <option value="{{ $subfleet_id }}" @if(request()->get('subfleet_id') == $subfleet_id) selected @endif>{{ $subfleet_label }}</option>
          @endforeach
        </select>
      </div>

      <div class="clear mt-3" style="margin-top: 10px;">
        <button type="submit" class="btn btn-outline-primary">@lang('common.find')</button>
        <a href="{{ route('frontend.flights.index') }}">@lang('common.reset')</a>
      </div>
      </form>
    </div>
  </div>
</div>
