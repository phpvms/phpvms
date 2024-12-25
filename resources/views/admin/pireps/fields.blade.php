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
      <h6><i class="fas fa-info-circle"></i>&nbsp;Flight Information</h6>
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
                  {{ Form::text('flight_number', null, ['placeholder' => 'Flight Number', 'class' => 'form-control']) }}
                  <p class="text-danger">{{ $errors->first('flight_number') }}</p>
                </div>
                <div class="col-sm-4">
                  {{ Form::text('route_code', null, ['placeholder' => 'Code (optional)', 'class' => 'form-control']) }}
                  <p class="text-danger">{{ $errors->first('route_code') }}</p>
                </div>
                <div class="col-sm-4">
                  {{ Form::text('route_leg', null, ['placeholder' => 'Leg (optional)', 'class' => 'form-control']) }}
                  <p class="text-danger">{{ $errors->first('route_leg') }}</p>
                </div>
              </div>
            @endif
          </div>
          <div class="form-group col-sm-3">
            {{ Form::label('flight_type', 'Flight Type') }}
            @if($pirep->read_only)
              <p>{{ $pirep->flight_type.' | '.\App\Models\Enums\FlightType::label($pirep->flight_type) }}</p>
              {{ Form::hidden('flight_type') }}
            @else
              {{ Form::select('flight_type', \App\Models\Enums\FlightType::select(), null, ['class' => 'form-control select2']) }}
            @endif
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
      <h6><i class="fas fa-info-circle"></i>&nbsp;Pirep Details</h6>
      <div class="form-container-body">
        <div class="row">
          <div class="form-group col-sm-3">
            {{ Form::label('airline_id', 'Airline') }}
            @if($pirep->read_only)
              <p>{{ $pirep->airline->name }}</p>
              {{ Form::hidden('airline_id') }}
            @else
              {{ Form::select('airline_id', $airlines_list, null, ['class' => 'form-control select2']) }}
              <p class="text-danger">{{ $errors->first('airline_id') }}</p>
            @endif
          </div>
          <div class="form-group col-sm-3">
            {{ Form::label('aircraft_id', 'Aircraft:') }}
            @if($pirep->read_only)
              <p>{{ optional($pirep->aircraft)->ident }}</p>
              {{ Form::hidden('aircraft_id') }}
            @else
              {{ Form::select('aircraft_id', $aircraft_list, null, ['id' => 'aircraft_select', 'class' => 'form-control select2']) }}
              <p class="text-danger">{{ $errors->first('aircraft_id') }}</p>
            @endif
          </div>
          <div class="form-group col-sm-3">
            {{ Form::label('dpt_airport_id', 'Departure Airport:') }}
            @if($pirep->read_only)
              <p>{{ $pirep->dpt_airport_id }}@if(filled($pirep->dpt_airport)) - {{ $pirep->dpt_airport->name }}@endif</p>
              {{ Form::hidden('dpt_airport_id') }}
            @else
              {{ Form::select('dpt_airport_id', $airports_list, null, ['class' => 'form-control airport_search']) }}
              <p class="text-danger">{{ $errors->first('dpt_airport_id') }}</p>
            @endif
          </div>
          <div class="form-group col-sm-3">
            {{ Form::label('arr_airport_id', 'Arrival Airport:') }}
            @if($pirep->read_only)
              <p>{{ $pirep->arr_airport->id }}@if(filled($pirep->arr_airport)) - {{ $pirep->arr_airport->name }}@endif</p>
              {{ Form::hidden('arr_airport_id') }}
            @else
              {{ Form::select('arr_airport_id', $airports_list, null, ['class' => 'form-control airport_search']) }}
              <p class="text-danger">{{ $errors->first('arr_airport_id') }}</p>
            @endif
          </div>
        </div>
        <div class="row">
          <!-- Planned Flight Time Field -->
          <div class="form-group col-sm-1">
            {{ Form::label('planned_flight_time', 'Pln.Time:') }}
            <div class="row">
              <div class="col-sm-12">
                @php $ft = App\Support\Units\Time::minutesToTimeString($pirep->planned_flight_time); @endphp
                {{ Form::text('planned_flight_time', $ft, ['class' => 'form-control', 'disabled' => 'disabled']) }}
              </div>
            </div>
          </div>
          <!-- Flight Time Field -->
          <div class="form-group col-sm-1">
            {{ Form::label('flight_time', 'Flt.Time (h:m):') }}
            <div class="row">
              <div class="col-sm-6">
                {{ Form::number('hours', null, ['class' => 'form-control', 'placeholder' => 'hours']) }}
                <p class="text-danger">{{ $errors->first('hours') }}</p>
              </div>
              <div class="col-sm-6">
                {{ Form::number('minutes', null, ['class' => 'form-control', 'placeholder' => 'minutes']) }}
                <p class="text-danger">{{ $errors->first('minutes') }}</p>
              </div>
            </div>
          </div>
          <!-- Block Fuel Field -->
          <div class="form-group col-sm-1">
            {{ Form::label('block_fuel', 'Block Fuel (lbs):') }}
            <div class="row">
              <div class="col-sm-12">
                <input class="form-control" type="number" name="block_fuel" value="{{ $pirep->block_fuel->internal() ?? null }}" step="0.01" />
                <p class="text-danger">{{ $errors->first('block_fuel') }}</p>
              </div>
            </div>
          </div>
          <!-- Fuel Used Field -->
          <div class="form-group col-sm-1">
            {{ Form::label('fuel_used', 'Used Fuel (lbs):') }}
            <div class="row">
              <div class="col-sm-12">
                <input class="form-control" type="number" name="fuel_used" value="{{ $pirep->fuel_used->internal() ?? null }}" step="0.01" />
                <p class="text-danger">{{ $errors->first('fuel_used') }}</p>
              </div>
            </div>
          </div>
          <!-- Planned/Flight Level Field -->
          <div class="form-group col-sm-1">
            {{ Form::label('planned_level', 'Pln.Level (ft):') }}
            <div class="row">
              <div class="col-sm-12">
                {{ Form::text('planned_level', optional($pirep->flight)->level, ['class' => 'form-control', 'disabled' => 'disabled']) }}
              </div>
            </div>
          </div>
          <!-- Level Field -->
          <div class="form-group col-sm-1">
            {{ Form::label('level', 'Flt.Level (ft):') }}
            <div class="row">
              <div class="col-sm-12">
                {{ Form::number('level', null, ['class' => 'form-control', 'min' => 0]) }}
                <p class="text-danger">{{ $errors->first('level') }}</p>
              </div>
            </div>
          </div>
          <!-- Planned Distance -->
          <div class="form-group col-sm-1">
            {{ Form::label('planned_distance', 'Pln.Dist. (nmi):') }}
            <div class="row">
              <div class="col-sm-12">
                <input class="form-control" type="number" name="planned_distance" value="{{ $pirep->planned_distance->internal() ?? null }}" min="0" step="0.01" readonly/>
                <p class="text-danger">{{ $errors->first('planned_distance') }}</p>
              </div>
            </div>
          </div>
          <!-- Distance -->
          <div class="form-group col-sm-1">
            {{ Form::label('distance', 'Flt.Dist. (nmi):') }}
            <div class="row">
              <div class="col-sm-12">
                <input class="form-control" type="number" name="distance" value="{{ $pirep->distance->internal() ?? null}}" min="0" step="0.01" />
                <p class="text-danger">{{ $errors->first('distance') }}</p>
              </div>
            </div>
          </div>
          <!-- Landing Rate -->
          <div class="form-group col-sm-1">
            {{ Form::label('landing_rate', 'Landing Rate:') }}
            <div class="row">
              <div class="col-sm-12">
                {{ Form::text('landing_rate', $pirep->landing_rate, ['class' => 'form-control', 'disabled' => 'disabled']) }}
              </div>
            </div>
          </div>
          <!-- Score -->
          <div class="form-group col-sm-1">
            {{ Form::label('score', 'Score:') }}
            <div class="row">
              <div class="col-sm-12">
                {{ Form::number('score', null, ['class' => 'form-control', 'min' => 0, 'max' => 100]) }}
                <p class="text-danger">{{ $errors->first('score') }}</p>
              </div>
            </div>
          </div>
        </div>
        <div class="row">
          <!-- Route Field -->
          <div class="form-group col-sm-8">
            {{ Form::label('route', 'Route:') }}
            {{ Form::textarea('route', null, ['class' => 'form-control']) }}
            <p class="text-danger">{{ $errors->first('route') }}</p>
          </div>
          <!-- Notes Field -->
          <div class="form-group col-sm-4">
            {{ Form::label('notes', 'Notes:') }}
            {{ Form::textarea('notes', null, ['class' => 'form-control']) }}
            <p class="text-danger">{{ $errors->first('notes') }}</p>
          </div>
        </div>
        @if(filled(optional($pirep->flight)->route))
          <div class="row">
            <!-- Flight Route -->
            <div class="form-group col-sm-8">
              {{ Form::label('planned_route', 'Provided Route:') }}
              {{ Form::textarea('planned_route', optional($pirep->flight)->route, ['class' => 'form-control', 'rows' => 4, 'disabled' => 'disabled']) }}
            </div>
          </div>
        @endif
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
{{-- FARES --}}
<div class="row">
  <div class="col-sm-12">
    <div class="form-container">
      <h6><i class="fas fa-info-circle"></i>&nbsp;Fares</h6>
      <div class="form-container-body">
        <div id="fares_container">
          @include('admin.pireps.fares')
        </div>
      </div>
    </div>
  </div>
</div>
{{-- CUSTOM FIELDS --}}
<div class="row">
  <div class="col-sm-12">
    <div class="form-container">
      <h6><i class="fas fa-info-circle"></i>&nbsp;Fields</h6>
      <div class="form-container-body">
        {{-- You don't want to change this ID unless you don't want the fares form to work :) --}}
        @include('admin.pireps.field_values')
      </div>
    </div>
  </div>
</div>
