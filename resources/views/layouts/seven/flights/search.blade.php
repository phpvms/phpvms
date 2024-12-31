<div class="row">
  <div class="col-12">
    <div class="form-group search-form">
      <form method="get" action="{{ route('frontend.flights.search') }}">
        @csrf
        <div>
          <div class="mb-3">
            <label for="airline_id" class="form-label">@lang('common.airline')</label>
            <select name="airline_id" id="airline_id" class="form-select">
              @foreach($airlines as $airline_id => $airline_label)
                <option value="{{ $airline_id }}" @if(request()->get('airline_id') == $airline_id) selected @endif>{{ $airline_label }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="mb-3">
          <label for="flight_type" class="form-label">@lang('flights.flighttype')</label>
          <select name="flight_type" id="flight_type" class="form-select">
            @foreach($flight_types as $flight_type_id => $flight_type_label)
              <option value="{{ $flight_type_id }}" @if(request()->get('flight_type') == $flight_type_id) selected @endif>{{ $flight_type_label }}</option>
            @endforeach
          </select>
        </div>

        <div class="mb-3">
          <label for="flight_number" class="form-label">@lang('flights.flightnumber')</label>
          <input type="text" name="flight_number" id="flight_number" class="form-control" value="{{ request()->get('flight_number') }}" />
        </div>

        <div class="mb-3">
          <label for="route_code" class="form-label">@lang('flights.code')</label>
          <input type="text" name="route_code" id="route_code" class="form-control" value="{{ request()->get('route_code') }}" />
        </div>

        <div class="mb-3">
          <label for="dep_icao" class="form-label">@lang('airports.departure')</label>
          <select name="dep_icao" placeholder="Type To Begin Search" id="dep_icao" class="form-select airport_search">
          </select>
        </div>

        <div class="mb-3">
          <label for="arr_icao" class="form-label">@lang('airports.arrival')</label>
          <select name="arr_icao" placeholder="Type To Begin Search" id="arr_icao" class="form-select airport_search">
          </select>
        </div>

        <div class="mb-3">
          <label for="subfleet_id" class="form-label">@lang('common.subfleet')</label>
          <select name="subfleet_id" id="subfleet_id" class="form-select select2">
            @foreach($subfleets as $subfleet_id => $subfleet_label)
              <option value="{{ $subfleet_id }}" @if(request()->get('subfleet_id') == $subfleet_id) selected @endif>{{ $subfleet_label }}</option>
            @endforeach
          </select>
        </div>

        @if(filled($type_ratings))
          <div class="mb-3">
            <label for="type_rating_id" class="form-label">Type Rating</label>
            <select name="type_rating_id" id="type_rating_id" class="form-select select2">
              <option value=""></option>
              @foreach($type_ratings as $tr)
                <option value="{{ $tr->id }}" @if(request()->get('type_rating_id') == $tr->id) selected @endif>{{ $tr->type.' | '.$tr->name }}</option>
              @endforeach
            </select>
          </div>
        @endif

        @if(filled($icao_codes))
          <div class="mb-3">
            <label for="icao_type" class="form-label">ICAO Type</label>
            <select name="icao_type" id="icao_type" class="form-select select2">
              <option value=""></option>
              @foreach($icao_codes as $icao)
                <option value="{{ $icao }}" @if(request()->get('icao_type') == $icao) selected @endif>{{ $icao }}</option>
              @endforeach
            </select>
          </div>
        @endif

        <div class="d-flex justify-content-between mt-3">
          <button type="submit" class="btn btn-primary">@lang('common.find')</button>
          <a href="{{ route('frontend.flights.index') }}" class="btn btn-secondary">@lang('common.reset')</a>
        </div>
      </form>
    </div>
  </div>
</div>
