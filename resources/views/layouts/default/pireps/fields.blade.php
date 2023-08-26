{{--

NOTE ABOUT THIS VIEW

The fields that are marked "read-only", make sure the read-only status doesn't change!
If you make those fields editable, after they're in a read-only state, it can have
an impact on your stats and financials, and will require a recalculation of all the
flight reports that have been filed. You've been warned!

--}}
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
  <div class="col-8">
    <div class="form-container">
      <h6><i class="fas fa-info-circle"></i>
        &nbsp;@lang('pireps.flightinformations')
      </h6>
      <div class="form-container-body">
        <div class="row">
          <div class="col-sm-4">
            {{ Form::label('airline_id', __('common.airline')) }}
            @if(!empty($pirep) && $pirep->read_only)
              <p>{{ $pirep->airline->name }}</p>
              {{ Form::hidden('airline_id') }}
            @else
              <div class="input-group input-group form-group">
                {{ Form::select('airline_id', $airline_list, null, [
                    'class' => 'custom-select select2',
                    'style' => 'width: 100%',
                    'readonly' => (!empty($pirep) && $pirep->read_only),
                ]) }}
              </div>
              <p class="text-danger">{{ $errors->first('airline_id') }}</p>
            @endif
          </div>
          <div class="col-sm-4">
            {{ Form::label('flight_number', __('pireps.flightident')) }}
            @if(!empty($pirep) && $pirep->read_only)
              <p>{{ $pirep->ident }}
                {{ Form::hidden('flight_number') }}
                {{ Form::hidden('flight_code') }}
                {{ Form::hidden('flight_leg') }}
              </p>
            @else
              <div class="input-group input-group-sm mb3">
                {{ Form::text('flight_number', null, [
                    'placeholder' => __('flights.flightnumber'),
                    'class' => 'form-control',
                    'readonly' => (!empty($pirep) && $pirep->read_only),
                ]) }}
                &nbsp;
                {{ Form::text('route_code', null, [
                    'placeholder' => __('pireps.codeoptional'),
                    'class' => 'form-control',
                    'readonly' => (!empty($pirep) && $pirep->read_only),
                ]) }}
                &nbsp;
                {{ Form::text('route_leg', null, [
                    'placeholder' => __('pireps.legoptional'),
                    'class' => 'form-control',
                    'readonly' => (!empty($pirep) && $pirep->read_only),
                ]) }}
              </div>
              <p class="text-danger">{{ $errors->first('flight_number') }}</p>
              <p class="text-danger">{{ $errors->first('route_code') }}</p>
              <p class="text-danger">{{ $errors->first('route_leg') }}</p>
            @endif
          </div>
          <div class="col-lg-4">
            {{ Form::label('flight_type', __('flights.flighttype')) }}
            @if(!empty($pirep) && $pirep->read_only)
              <p>{{ \App\Models\Enums\FlightType::label($pirep->flight_type) }}</p>
              {{ Form::hidden('flight_type') }}
            @else
              <div class="form-group">
                {{ Form::select('flight_type',
                    \App\Models\Enums\FlightType::select(), null, [
                        'class' => 'custom-select select2',
                        'style' => 'width: 100%',
                        'readonly' => (!empty($pirep) && $pirep->read_only),
                    ])
                }}
              </div>
              <p class="text-danger">{{ $errors->first('flight_type') }}</p>
            @endif
          </div>
        </div>

        <div class="row">
          <div class="col-6">
            {{ Form::label('hours', __('flights.flighttime')) }}
            @if(!empty($pirep) && $pirep->read_only)
              <p>
                {{ $pirep->hours.' '.trans_choice('common.hour', $pirep->hours) }}
                , {{ $pirep->minutes.' '.trans_choice('common.minute', $pirep->minutes) }}
                {{ Form::hidden('hours') }}
                {{ Form::hidden('minutes') }}
              </p>
            @else
              <div class="input-group input-group-sm" style="max-width: 400px;">
                {{ Form::number('hours', null, [
                        'class' => 'form-control',
                        'placeholder' => trans_choice('common.hour', 2),
                        'min' => '0',
                        'readonly' => (!empty($pirep) && $pirep->read_only),
                ]) }}

                {{ Form::number('minutes', null, [
                    'class' => 'form-control',
                    'placeholder' => trans_choice('common.minute', 2),
                    'min' => 0,
                    'readonly' => (!empty($pirep) && $pirep->read_only),
                    ]) }}
              </div>
              <p class="text-danger">{{ $errors->first('hours') }}</p>
              <p class="text-danger">{{ $errors->first('minutes') }}</p>
            @endif
          </div>
          <div class="col-6">
            {{ Form::label('level', __('flights.level')) }} ({{config('phpvms.internal_units.altitude')}})
            @if(!empty($pirep) && $pirep->read_only)
              <p>{{ $pirep->level }}</p>
            @else
              <div class="input-group input-group-sm form-group">
                {{ Form::number('level', null, [
                    'class' => 'form-control',
                    'min' => '0',
                    'step' => '0.01',
                    'readonly' => (!empty($pirep) && $pirep->read_only),
                    ]) }}
              </div>
              <p class="text-danger">{{ $errors->first('level') }}</p>
            @endif
          </div>
        </div>
      </div>
    </div>


    <div class="form-container">
      <h6><i class="fas fa-globe"></i>
        &nbsp;@lang('pireps.deparrinformations')
      </h6>
      <div class="form-container-body">
        <div class="row">
          <div class="col-6">
            {{ Form::label('dpt_airport_id', __('airports.departure')) }}
            @if(!empty($pirep) && $pirep->read_only)
              {{ $pirep->dpt_airport->name }}
              (<a href="{{route('frontend.airports.show', [
                                    'id' => $pirep->dpt_airport->icao
                                    ])}}">{{$pirep->dpt_airport->icao}}</a>)
              {{ Form::hidden('dpt_airport_id') }}
            @else
              <div class="form-group">
                {{ Form::select('dpt_airport_id', $airport_list, null, [
                        'class' => 'custom-select airport_search',
                        'style' => 'width: 100%',
                        'readonly' => (!empty($pirep) && $pirep->read_only),
                ]) }}
              </div>
              <p class="text-danger">{{ $errors->first('dpt_airport_id') }}</p>
            @endif
          </div>

          <div class="col-6">
            {{ Form::label('arr_airport_id', __('airports.arrival')) }}
            @if(!empty($pirep) && $pirep->read_only)
              {{ $pirep->arr_airport->name }}
              (<a href="{{route('frontend.airports.show', [
                                    'id' => $pirep->arr_airport->icao
                                    ])}}">{{$pirep->arr_airport->icao}}</a>)
              {{ Form::hidden('arr_airport_id') }}
            @else
              <div class="input-group input-group-sm form-group">
                {{ Form::select('arr_airport_id', $airport_list, null, [
                        'class' => 'custom-select airport_search',
                        'style' => 'width: 100%',
                        'readonly' => (!empty($pirep) && $pirep->read_only),
                ]) }}
              </div>
              <p class="text-danger">{{ $errors->first('arr_airport_id') }}</p>
            @endif
          </div>
        </div>
      </div>
    </div>

    <div class="form-container">
      <h6><i class="fab fa-avianex"></i>
        &nbsp;@lang('pireps.aircraftinformations')
      </h6>
      <div class="form-container-body">
        <div class="row">
          <div class="col-4">
            {{ Form::label('aircraft_id', __('common.aircraft')) }}
            @if(!empty($pirep) && $pirep->read_only)
              <p>{{ $pirep->aircraft->name }}</p>
              {{ Form::hidden('aircraft_id') }}
            @else
              <div class="input-group input-group-sm form-group">
                {{-- You probably don't want to change this ID if you want the fare select to work --}}
                {{ Form::select('aircraft_id', $aircraft_list, null, [
                    'id' => 'aircraft_select',
                    'class' => 'custom-select select2',
                    'readonly' => (!empty($pirep) && $pirep->read_only),
                    ]) }}
              </div>
              <p class="text-danger">{{ $errors->first('aircraft_id') }}</p>
            @endif
          </div>
          <div class="col-4">
            {{ Form::label('block_fuel', __('pireps.block_fuel')) }} ({{setting('units.fuel')}})
            @if(!empty($pirep) && $pirep->read_only)
              <p>{{ $pirep->block_fuel }}</p>
            @else
              <div class="input-group input-group-sm form-group">
                {{ Form::number('block_fuel', null, [
                    'class' => 'form-control',
                    'min' => '0',
                    'step' => '0.01',
                    'readonly' => (!empty($pirep) && $pirep->read_only),
                    ]) }}
              </div>
              <p class="text-danger">{{ $errors->first('block_fuel') }}</p>
            @endif
          </div>
          <div class="col-4">
            {{ Form::label('fuel_used', __('pireps.fuel_used')) }} ({{setting('units.fuel')}})
            @if(!empty($pirep) && $pirep->read_only)
              <p>{{ $pirep->fuel_used }}</p>
            @else
              <div class="input-group input-group-sm form-group">
                {{ Form::number('fuel_used', null, [
                    'class' => 'form-control',
                    'min' => '0',
                    'step' => '0.01',
                    'readonly' => (!empty($pirep) && $pirep->read_only),
                    ]) }}
              </div>
              <p class="text-danger">{{ $errors->first('fuel_used') }}</p>
            @endif
          </div>
        </div>
      </div>
    </div>

    <div id="fares_container" class="form-container">
      @include('pireps.fares')
    </div>

    <div class="form-container">
      <h6><i class="far fa-comments"></i>
        &nbsp;@lang('flights.route')
      </h6>
      <div class="form-container-body">
        <div class="row">
          <div class="col">
            <div class="input-group input-group-sm form-group">
              {{ Form::textarea('route', null, [
                  'class' => 'form-control',
                  'placeholder' => __('flights.route'),
                  'readonly' => (!empty($pirep) && $pirep->read_only),
              ]) }}
              <p class="text-danger">{{ $errors->first('route') }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="form-container">
      <h6><i class="far fa-comments"></i>
        &nbsp;{{ trans_choice('common.remark', 2) }}
      </h6>
      <div class="form-container-body">
        <div class="row">
          <div class="col">
            <div class="input-group input-group-sm form-group">
              {{ Form::textarea('notes', null, ['class' => 'form-control', 'placeholder' => trans_choice('common.note', 2)]) }}
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
  <div class="col-4">
    <div class="form-container">
      <h6><i class="fab fa-wpforms"></i>
        &nbsp;{{ trans_choice('common.field', 2) }}
      </h6>
      <div class="form-container-body">
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
</div>
<div class="row">
  <div class="col-sm-12">
    <div class="float-right">
      <div class="form-group">

        {{ Form::hidden('flight_id') }}
        {{ Form::hidden('sb_id', $simbrief_id) }}

        @if(isset($pirep) && !$pirep->read_only)
          {{ Form::button(__('pireps.deletepirep'), [
              'name' => 'submit',
              'value' => 'Delete',
              'class' => 'btn btn-warning',
              'type' => 'submit',
              'onclick' => "return confirm('Are you sure you want to delete this PIREP?')"])
              }}
        @endif

        {{ Form::button(__('pireps.savepirep'), [
                'name' => 'submit',
                'value' => 'Save',
                'class' => 'btn btn-info',
                'type' => 'submit'])
            }}

        @if(!isset($pirep) || (filled($pirep) && !$pirep->read_only))
          {{ Form::button(__('pireps.submitpirep'), [
              'name' => 'submit',
              'value' => 'Submit',
              'class' => 'btn btn-success',
              'type' => 'submit'])
          }}
        @endif
      </div>
    </div>
  </div>
</div>
