{{--

NOTE ABOUT THIS VIEW

The fields that are marked "read-only", make sure the read-only status doesn't change!
If you make those fields editable, after they're in a read-only state, it can have
an impact on your stats and financials, and will require a recalculation of all the
flight reports that have been filed. You've been warned!

--}}
@php
  $custom_fields_exist = (isset($pirep) && $pirep->fields && $pirep->fields->isNotEmpty()) || (!isset($pirep) && $pirep_fields && $pirep_fields->isNotEmpty());

  $form_width = $custom_fields_exist ? 'col-lg-8' : 'col-lg-12';
@endphp
@if(!empty($pirep) && $pirep->read_only)
  <div class="row">
    <div class="col-sm-12">
      @component('components.info')
        @lang('pireps.fieldsreadonly')
      @endcomponent
    </div>
  </div>
@endif
<div class="row">
  <div class="{{ $form_width }}">
    <div class="card mb-3">
      <div class="card-header">
        @lang('pireps.flightinformations')
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-sm-4">
            <label for="airline_id">@lang('common.airline')</label>
            @if(!empty($pirep) && $pirep->read_only)
              <p>{{ $pirep->airline->name }}</p>
              <input type="hidden" name="airline_id" value="{{ $pirep->airline_id }}" />
            @else
              <div class="form-group">
                <select name="airline_id" id="airline_id" class="form-select select2">
                  @foreach($airline_list as $airline_id => $airline_label)
                    <option value="{{ $airline_id }}" @if(!empty($pirep) && $airline_id === $pirep->airline_id) selected @endif>{{ $airline_label }}</option>
                  @endforeach
                </select>
              </div>
              <p class="text-danger">{{ $errors->first('airline_id') }}</p>
            @endif
          </div>
          <div class="col-sm-4">
            <label for="flight_number">@lang('pireps.flightident')</label>
            @if(!empty($pirep) && $pirep->read_only)
              <p>{{ $pirep->ident }}
                <input type="hidden" name="flight_number" value="{{ $pirep->flight_number }}" />
                <input type="hidden" name="flight_code" value="{{ $pirep->flight_code }}" />
                <input type="hidden" name="flight_leg" value="{{ $pirep->flight_leg }}" />
              </p>
            @else
              <div class="input-group mb-3">
                <input
                  type="text"
                  name="flight_number"
                  id="flight_number"
                  class="form-control"
                  @if(!empty($pirep) && $pirep->read_only) readonly @endif
                  value="{{ !empty($pirep) ? $pirep->flight_number : old('flight_number') }}"
                  placeholder="@lang('flights.flightnumber')"
                />

                <input
                  type="text"
                  name="route_code"
                  id="route_code"
                  class="form-control"
                  @if(!empty($pirep) && $pirep->read_only) readonly @endif
                  value="{{ !empty($pirep) ? $pirep->route_code : old('route_code') }}"
                  placeholder="@lang('pireps.codeoptional')"
                />

                <input
                  type="text"
                  name="route_leg"
                  id="route_leg"
                  class="form-control"
                  @if(!empty($pirep) && $pirep->route_leg) readonly @endif
                  value="{{ !empty($pirep) ? $pirep->route_leg : old('route_leg') }}"
                  placeholder="@lang('pireps.legoptional')"
                />
              </div>
              <p class="text-danger">{{ $errors->first('flight_number') }}</p>
              <p class="text-danger">{{ $errors->first('route_code') }}</p>
              <p class="text-danger">{{ $errors->first('route_leg') }}</p>
            @endif
          </div>
          <div class="col-lg-4">
            <label for="flight_type">@lang('flights.flighttype')</label>
            @if(!empty($pirep) && $pirep->read_only)
              <p>{{ \App\Models\Enums\FlightType::label($pirep->flight_type) }}</p>
              <input type="hidden" name="flight_type" value="{{ $pirep->flight_type }}" />
            @else
              <div class="form-group">
                <select
                  name="flight_type"
                  id="flight_type"
                  class="form-select select2"
                >
                  @foreach(\App\Models\Enums\FlightType::select() as $flight_type_id => $flight_type_label)
                    <option value="{{ $flight_type_id }}" @if(!empty($pirep) && $pirep->flight_type == $flight_type_id) selected @endif>{{ $flight_type_label }}</option>
                  @endforeach
                </select>
              </div>
              <p class="text-danger">{{ $errors->first('flight_type') }}</p>
            @endif
          </div>
        </div>

        <div class="row">
          <div class="col-6">
            <label for="hours">@lang('flights.flighttime')</label>
            @if(!empty($pirep) && $pirep->read_only)
              <p>
                {{ $pirep->hours.' '.trans_choice('common.hour', $pirep->hours) }}
                , {{ $pirep->minutes.' '.trans_choice('common.minute', $pirep->minutes) }}
                <input type="hidden" name="hours" value="{{ $pirep->hours }}" />
                <input type="hidden" name="minutes" value="{{ $pirep->minutes }}" />
              </p>
            @else
              <div class="input-group" style="max-width: 400px;">
                <input type="number"
                       name="hours"
                       id="hours"
                       class="form-control"
                       @if(!empty($pirep) && $pirep->read_only) readonly @endif
                       placeholder="{{ trans_choice('common.hour', 2) }}"
                       min="0"
                       value="{{ !empty($pirep) ? $pirep->hours : old('hours') }}"
                />

                <input type="number"
                       name="minutes"
                       id="minutes"
                       class="form-control"
                       @if(!empty($pirep) && $pirep->read_only) readonly @endif
                       placeholder="{{ trans_choice('common.minute', 2) }}"
                       min="0"
                       value="{{ !empty($pirep) ? $pirep->minutes : old('minutes') }}"
                />
              </div>
              <p class="text-danger">{{ $errors->first('hours') }}</p>
              <p class="text-danger">{{ $errors->first('minutes') }}</p>
            @endif
          </div>
          <div class="col-6">
            <label for="level">@lang('flights.level') ({{config('phpvms.internal_units.altitude')}})</label>
            @if(!empty($pirep) && $pirep->read_only)
              <p>{{ $pirep->level }}</p>
            @else
              <div class="form-group">
                <input type="number"
                       name="level"
                       id="level"
                       class="form-control"
                       @if(!empty($pirep) && $pirep->read_only) readonly @endif
                       min="0"
                       step="0.01"
                       value="{{ !empty($pirep) ? $pirep->level : old('level') }}"
                />
              </div>
              <p class="text-danger">{{ $errors->first('level') }}</p>
            @endif
          </div>
        </div>
      </div>
    </div>


    <div class="card mb-3">
      <div class="card-header">
        @lang('pireps.deparrinformations')
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-6">
            <label for="dpt_airport_id">@lang('airports.departure')</label>
            @if(!empty($pirep) && ($pirep->read_only || request()->has('flight_id')))
              {{ $pirep->dpt_airport->name }}
              (<a href="{{route('frontend.airports.show', [
                                    'id' => $pirep->dpt_airport->icao
                                    ])}}">{{$pirep->dpt_airport->icao}}</a>)
              <input type="hidden" name="dpt_airport_id" value="{{ $pirep->dpt_airport_id }}" />
            @else
              <div class="form-group">
                <select
                  name="dpt_airport_id"
                  id="dpt_airport_id"
                  class="form-select airport_search"
                >
                  @foreach($airport_list as $dpt_airport_id => $dpt_airport_label)
                    <option value="{{ $dpt_airport_id }}" @if(!empty($pirep) && $pirep->dpt_airport_id == $dpt_airport_id) selected @endif>{{ $dpt_airport_label }}</option>
                  @endforeach
                </select>
              </div>
              <p class="text-danger">{{ $errors->first('dpt_airport_id') }}</p>
            @endif
          </div>

          <div class="col-6">
            <label for="arr_airport_id">@lang('airports.arrival')</label>
            @if(!empty($pirep) && ($pirep->read_only || request()->has('flight_id')))
              {{ $pirep->arr_airport->name }}
              (<a href="{{route('frontend.airports.show', [
                                    'id' => $pirep->arr_airport->icao
                                    ])}}">{{$pirep->arr_airport->icao}}</a>)
              <input type="hidden" name="arr_airport_id" value="{{ $pirep->arr_airport_id }}" />
            @else
              <div class="form-group">
                <select
                  name="arr_airport_id"
                  id="arr_airport_id"
                  class="form-select airport_search"
                >
                  @foreach($airport_list as $arr_airport_id => $arr_airport_label)
                    <option value="{{ $arr_airport_id }}" @if(!empty($pirep) && $pirep->arr_airport_id == $arr_airport_id) selected @endif>{{ $arr_airport_label }}</option>
                  @endforeach
                </select>
              </div>
              <p class="text-danger">{{ $errors->first('arr_airport_id') }}</p>
            @endif
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">
        @lang('pireps.aircraftinformations')
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-4">
            <label for="aircraft_id">@lang('common.aircraft')</label>
            @if(!empty($pirep) && $pirep->read_only)
              <p>{{ $pirep->aircraft->name }}</p>
              <input type="hidden" name="aircraft_id" value="{{ $pirep->aircraft_id }}" />
            @else
              <div class="form-group">
                {{-- You probably don't want to change this ID if you want the fare select to work --}}
                <select
                  name="aircraft_id"
                  id="aircraft_select"
                  class="form-select select2"
                >
                  @foreach($aircraft_list as $subfleet => $sf_aircraft)
                    @if ($subfleet === '')
                      <option value=""></option>
                    @else
                      @foreach($sf_aircraft as $aircraft_id => $aircraft_label)
                        <option value="{{ $aircraft_id }}" @if(!empty($pirep) && $pirep->aircraft_id == $aircraft_id) selected @endif>{{ $aircraft_label }}</option>
                      @endforeach
                    @endif
                  @endforeach
                </select>
              </div>
              <p class="text-danger">{{ $errors->first('aircraft_id') }}</p>
            @endif
          </div>
          <div class="col-4">
            <label for="block_fuel">@lang('pireps.block_fuel') ({{setting('units.fuel')}})</label>
            @if(!empty($pirep) && $pirep->read_only)
              <p>{{ $pirep->block_fuel }}</p>
            @else
              <div class="form-group">
                <input
                  type="number"
                  name="block_fuel"
                  id="block_fuel"
                  class="form-control"
                  min="0"
                  step="0.01"
                  @if(!empty($pirep) && $pirep->read_only) readonly @endif
                  value="{{ !empty($pirep) ? $pirep->block_fuel : old('block_fuel') }}"
                />
              </div>
              <p class="text-danger">{{ $errors->first('block_fuel') }}</p>
            @endif
          </div>
          <div class="col-4">
            <label for="fuel_used">@lang('pireps.fuel_used') ({{ setting('units.fuel') }})</label>
            @if(!empty($pirep) && $pirep->read_only)
              <p>{{ $pirep->fuel_used }}</p>
            @else
              <div class="form-group">
                <input
                  type="number"
                  name="fuel_used"
                  id="fuel_used"
                  class="form-control"
                  min="0"
                  step="0.01"
                  @if(!empty($pirep) && $pirep->read_only) readonly @endif
                  value="{{ !empty($pirep) ? $pirep->fuel_used : old('fuel_used') }}"
                />
              </div>
              <p class="text-danger">{{ $errors->first('fuel_used') }}</p>
            @endif
          </div>
        </div>
      </div>
    </div>
    <div id="fares_container">
      @include('pireps.fares')
    </div>
    
    <div class="card mb-3">
      <div class="card-header">
      @lang('flights.route') & {{ trans_choice('common.remark', 2) }}
      </div>
      <div class="card-body">
      <div class="row">
        <div class="col">
        <div class="form-group">
          <textarea name="route" id="route" placeholder="@lang('flights.route')" class="form-control">@if(!empty($pirep)){{ $pirep->route }}@else{{ old('route') }}@endif</textarea>
          <p class="text-danger">{{ $errors->first('route') }}</p>
        </div>
        </div>
      </div>
      <div class="row">
        <div class="col">
        <div class="form-group">
          <textarea name="notes" id="notes" placeholder="{{ trans_choice('common.note', 2) }}" class="form-control">@if(!empty($pirep)){{ $pirep->notes }}@else{{ old('notes') }}@endif</textarea>
          <p class="text-danger">{{ $errors->first('notes') }}</p>
        </div>
        </div>
      </div>
      </div>
    </div>
  </div>

  {{--
      Write out the custom fields, and label if they're required
  --}}
  @if($custom_fields_exist)
  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header">
        {{ trans_choice('common.field', 2) }}
      </div>
      <div class="card-body">
        <table class="table table-striped">
          
            @if(isset($pirep) && $pirep->fields)
              @each('pireps.custom_fields', $pirep->fields, 'field')
            @else
              @each('pireps.custom_fields', $pirep_fields, 'field')
            @endif
          
        </table>
      </div>
    </div>
  </div>
  @endif
</div>
<div class="row">
  <div class="col-sm-12">
    <div class="float-end">
      <div class="form-group">

        <input type="hidden" name="flight_id" value="{{ !empty($pirep) ? $pirep->flight_id : '' }}"/>
        <input type="hidden" name="sb_id" value="{{ $simbrief_id }}"/>

        @if(isset($pirep) && !$pirep->read_only)
          <button name="submit" type="submit" class="btn btn-warning" value="Delete" onclick="return confirm('Are you sure you want to delete this PIREP?')">
            @lang('pireps.deletepirep')
          </button>
        @endif

        <button name="submit" type="submit" class="btn btn-info" value="Save">
          @lang('pireps.savepirep')
        </button>

        @if(!isset($pirep) || (filled($pirep) && !$pirep->read_only))
          <button name="submit" type="submit" class="btn btn-success" value="Submit">
            @lang('pireps.submitpirep')
          </button>
        @endif
      </div>
    </div>
  </div>
</div>
