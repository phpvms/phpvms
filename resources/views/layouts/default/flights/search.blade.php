<h4 class="description">@lang('flights.search')</h4>
<div class="row">
  <div class="col-12">
    <div class="form-group search-form">
      {{ Form::open([
              'route' => 'frontend.flights.search',
              'method' => 'GET',
      ]) }}

      <div class="mt-1">
        <div class="form-group">
          <p>@lang('common.airline')</p>
          {{ Form::select('airline_id', $airlines, null , ['class' => 'form-control select2']) }}
        </div>
      </div>

      <div class="mt-1">
        <p>@lang('flights.flighttype')</p>
        {{ Form::select('flight_type', $flight_types, null , ['class' => 'form-control select2']) }}
      </div>

      <div class="mt-1">
        <p>@lang('flights.flightnumber')</p>
        {{ Form::text('flight_number', null, ['class' => 'form-control']) }}
      </div>

      <div class="mt-1">
        <p>@lang('flights.code')</p>
        {{ Form::text('route_code', null, ['class' => 'form-control']) }}
      </div>

      <div class="mt-1">
        <p>@lang('airports.departure')</p>
        {{ Form::select('dep_icao', [], null , ['class' => 'form-control airport_search']) }}
      </div>

      <div class="mt-1">
        <p>@lang('airports.arrival')</p>
        {{ Form::select('arr_icao', [], null , ['class' => 'form-control airport_search']) }}
      </div>

      <div class="mt-1">
        <p>@lang('common.subfleet')</p>
        {{ Form::select('subfleet_id', $subfleets, null , ['class' => 'form-control select2']) }}
      </div>

      <div class="clear mt-1" style="margin-top: 10px;">
        {{ Form::submit(__('common.find'), ['class' => 'btn btn-outline-primary']) }}&nbsp;
        <a href="{{ route('frontend.flights.index') }}">@lang('common.reset')</a>
      </div>
      {{ Form::close() }}
    </div>
  </div>
</div>
