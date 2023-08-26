@if(!empty($pirep) && $pirep->read_only)
  <div class="row">
    <div class="col-sm-12">
      @component('admin.components.info')
        Once a PIREP has been accepted/rejected, certain fields go into read-only mode.
      @endcomponent
    </div>
  </div>
@endif
<div class="row">
  <div class="col-xl-12">
    <div class="form-container">
      <h6><i class="fas fa-info-circle"></i>
        &nbsp;Flight Information
      </h6>
      <div class="form-container-body">

        <div class="row">
          <div class="form-group col-sm-6">
            {{ Form::label('flight_number', 'Flight Number/Route Code/Leg') }}
            @if($pirep->read_only)
              <p>{{ $pirep->ident }}
                {{ Form::hidden('flight_number') }}
                {{ Form::hidden('flight_code') }}
                {{ Form::hidden('flight_leg') }}
              </p>
            @else
              <div class="row">
                <div class="col-sm-4">
                  {{ Form::text('flight_number', null, [
                          'placeholder' => 'Flight Number',
                          'class' => 'form-control']) }}
                  <p class="text-danger">{{ $errors->first('flight_number') }}</p>
                </div>
                <div class="col-sm-4">
                  {{ Form::text('route_code', null, [
                          'placeholder' => 'Code (optional)',
                          'class' => 'form-control']) }}
                  <p class="text-danger">{{ $errors->first('route_code') }}</p>
                </div>
                <div class="col-sm-4">
                  {{ Form::text('route_leg', null, [
                          'placeholder' => 'Leg (optional)',
                          'class' => 'form-control']) }}
                  <p class="text-danger">{{ $errors->first('route_leg') }}</p>
                </div>
              </div>
            @endif
          </div>
          <div class="form-group col-sm-3">
            {{ Form::label('flight_type', 'Flight Type') }}
            {{ Form::select('flight_type',
                \App\Models\Enums\FlightType::select(),
                null, [
                    'class' => 'form-control select2',
                    'readonly' => $pirep->read_only
                ])
            }}
            <p class="text-danger">{{ $errors->first('flight_type') }}</p>
          </div>
          <div class="form-group col-sm-3">
            <p class="description">Filed Via:</p>
            {{ PirepSource::label($pirep->source) }}
            @if(filled($pirep->source_name))
              ({{ $pirep->source_name }})
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="row">
  <div class="col-xl-12">
    <div class="form-container">
      <h6><i class="fas fa-info-circle"></i>
        &nbsp;Flight Information
      </h6>
      <div class="form-container-body">

        <div class="row">
          <div class="form-group col-sm-3">
            {{ Form::label('airline_id', 'Airline') }}
            @if($pirep->read_only)
              <p>{{ $pirep->airline->name }}</p>
              {{ Form::hidden('airline_id') }}
            @else
              {{ Form::select('airline_id', $airlines_list, null, [
                      'class' => 'form-control select2',
                      'readonly' => $pirep->read_only]) }}
              <p class="text-danger">{{ $errors->first('airline_id') }}</p>
            @endif
          </div>
          <div class="form-group col-sm-3">
            {{ Form::label('aircraft_id', 'Aircraft:') }}
            @if($pirep->read_only)
              <p>{{ optional($pirep->aircraft)->name }}</p>
              {{ Form::hidden('aircraft_id') }}
            @else
              {{ Form::select('aircraft_id', $aircraft_list, null, [
                      'id' => 'aircraft_select',
                      'class' => 'form-control select2',
                      'readonly' => $pirep->read_only
                  ]) }}
              <p class="text-danger">{{ $errors->first('aircraft_id') }}</p>
            @endif
          </div>
          <div class="form-group col-sm-3">
            {{ Form::label('dpt_airport_id', 'Departure Airport:') }}
            @if($pirep->read_only)
              <p>{{ $pirep->dpt_airport_id }}@if($pirep->dpt_airport->name) - {{ $pirep->dpt_airport->name }}@endif
              </p>
              {{ Form::hidden('dpt_airport_id') }}
            @else
              {{ Form::select('dpt_airport_id', $airports_list, null, [
                      'class' => 'form-control airport_search',
                      'readonly' => $pirep->read_only]) }}
              <p class="text-danger">{{ $errors->first('dpt_airport_id') }}</p>
            @endif
          </div>

          <div class="form-group col-sm-3">
            {{ Form::label('arr_airport_id', 'Arrival Airport:') }}
            @if($pirep->read_only)
              <p>{{ $pirep->arr_airport->id }}@if($pirep->arr_airport->name) - {{ $pirep->arr_airport->name }}@endif
              </p>
              {{ Form::hidden('arr_airport_id') }}
            @else
              {{ Form::select('arr_airport_id', $airports_list, null, ['class' => 'form-control airport_search']) }}
              <p class="text-danger">{{ $errors->first('arr_airport_id') }}</p>
            @endif
          </div>
        </div>


        <div class="row">
          <!-- Flight Time Field -->
          <div class="form-group col-sm-3">
            {{ Form::label('flight_time', 'Flight Time (hours & minutes):') }}
            @if($pirep->read_only)
              <p>
                {{ $pirep->hours }} hours, {{ $pirep->minutes }} minutes
                {{ Form::hidden('hours') }}
                {{ Form::hidden('minutes') }}
              </p>
            @else
              <div class="row">
                <div class="col-sm-6">
                  {{ Form::number('hours', null, [
                          'class' => 'form-control',
                          'placeholder' => 'hours',
                          'readonly' => $pirep->read_only]) }}
                </div>
                <div class="col-sm-6">
                  {{ Form::number('minutes', null, [
                          'class' => 'form-control',
                          'placeholder' => 'minutes',
                          'readonly' => $pirep->read_only]) }}
                </div>
                <p class="text-danger">{{ $errors->first('hours') }}</p>
                <p class="text-danger">{{ $errors->first('minutes') }}</p>
              </div>
            @endif
          </div>

          <!-- Block Fuel Field -->
          <div class="form-group col-sm-2">
            {{ Form::label('block_fuel', 'Block Fuel:') }}
            <div class="row">
              <div class="col-sm-12">
                {{ Form::number('block_fuel', null, [
                    'class' => 'form-control',
                    'min' => 0,
                    'step' => '0.01',
                    ]) }}
                <p class="text-danger">{{ $errors->first('block_fuel') }}</p>
              </div>
            </div>
          </div>

          <!-- Fuel Used Field -->
          <div class="form-group col-sm-2">
            {{ Form::label('fuel_used', 'Fuel Used:') }}
            <div class="row">
              <div class="col-sm-12">
                {{ Form::number('fuel_used', null, [
                    'class' => 'form-control',
                    'min' => 0,
                    'step' => '0.01'
                    ]) }}
                <p class="text-danger">{{ $errors->first('fuel_used') }}</p>
              </div>
            </div>
          </div>

          <!-- Level Field -->
          <div class="form-group col-sm-5">
            {{ Form::label('level', 'Flight Level:') }}
            <div class="row">
              <div class="col-sm-12">
                {{ Form::number('level', null, ['class' => 'form-control', 'min' => 0]) }}
                <p class="text-danger">{{ $errors->first('level') }}</p>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="form-group col-sm-2">
            {{ Form::label('distance', 'Distance:') }}
            <div class="row">
              <div class="col-sm-12">
                {{ Form::number('distance', null, [
                    'class' => 'form-control',
                    'min' => 0,
                    'step' => '0.01'
                    ]) }}
                <p class="text-danger">{{ $errors->first('distance') }}</p>
              </div>
            </div>
          </div>

          <div class="form-group col-sm-2">
            {{ Form::label('distance', 'Planned Distance:') }}
            <div class="row">
              <div class="col-sm-12">
                {{ Form::number('planned_distance', null, [
                    'class' => 'form-control',
                    'min' => 0,
                    'step' => '0.01'
                    ]) }}
                <p class="text-danger">{{ $errors->first('planned_distance') }}</p>
              </div>
            </div>
          </div>

        </div>

        <div class="row">
          <!-- Route Field -->
          <div class="form-group col-sm-6">
            {{ Form::label('route', 'Route:') }}
            {{ Form::textarea('route', null, ['class' => 'form-control']) }}
            <p class="text-danger">{{ $errors->first('route') }}</p>
          </div>

          <!-- Notes Field -->
          <div class="form-group col-sm-6">
            {{ Form::label('notes', 'Notes:') }}
            {{ Form::textarea('notes', null, ['class' => 'form-control']) }}
            <p class="text-danger">{{ $errors->first('notes') }}</p>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

{{--
    FARES
--}}
<div class="row">
  <div class="col-sm-12">
    <div class="form-container">
      <h6><i class="fas fa-info-circle"></i>
        &nbsp;fares
      </h6>
      <div class="form-container-body">
        <div id="fares_container">
          @include('admin.pireps.fares')
        </div>
      </div>
    </div>
  </div>
</div>

{{--
    CUSTOM FIELDS
--}}

<div class="row">
  <div class="col-sm-12">
    <div class="form-container">
      <h6><i class="fas fa-info-circle"></i>
        &nbsp;fields
      </h6>
      <div class="form-container-body">
        {{-- You don't want to change this ID unless you don't want the fares form to work :) --}}
        @include('admin.pireps.field_values')
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="form-group col-sm-12">
    <div class="pull-right">
      {{ Form::button('Save', ['type' => 'submit', 'class' => 'btn btn-info']) }}
    </div>
  </div>
</div>
