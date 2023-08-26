<div class="row">
  <div class="col-12">
    <div class="form-container">
      <h6><i class="fas fa-info-circle"></i>
        &nbsp;Flight Information
      </h6>
      <div class="form-container-body">
        <div class="row">
          <div class="form-group col-sm-3">
            {{ Form::label('airline_id', 'Airline:') }}&nbsp;<span
              class="required">*</span>
            {{ Form::select('airline_id', $airlines, null , ['class' => 'form-control select2']) }}
            <p class="text-danger">{{ $errors->first('airline_id') }}</p>
          </div>

          <!-- Flight Number Field -->
          <div class="form-group col-sm-3">
            {{ Form::label('flight_number', 'Flight Number/Code/Leg') }}&nbsp;<span class="required">*</span>

            <div class="input-group input-group-sm mb3">
              {{ Form::text('flight_number', null, ['class' => 'form-control', 'style' => 'width: 33%']) }}
              {{ Form::text('route_code', null, ['class'=>'form-control', 'placeholder'=>'optional', 'style' => 'width: 33%']) }}
              {{ Form::text('route_leg', null, ['class'=>'form-control', 'placeholder'=>'optional', 'style' => 'width: 33%']) }}
            </div>

            <p class="text-danger">{{ $errors->first('flight_number') }}</p>
            <p class="text-danger">{{ $errors->first('route_code') }}</p>
            <p class="text-danger">{{ $errors->first('route_leg') }}</p>

          </div>

          <!-- Callsign Field -->
          <div class="form-group input-group-sm col-sm-2">
            {{ Form::label('callsign', 'Callsign:') }}
            {{ Form::text('callsign', null, ['class'=>'form-control', 'placeholder'=>'optional', 'maxlength' => 4]) }}
            <p class="text-danger">{{ $errors->first('callsign') }}</p>
          </div>

          <!-- Flight Type Field -->
          <div class="form-group col-sm-2">
            {{ Form::label('level', 'Flight Type:') }}&nbsp;<span class="required">*</span>
            {{ Form::select('flight_type', $flight_types, null, ['class' => 'form-control select2']) }}
            <p class="text-danger">{{ $errors->first('flight_type') }}</p>
          </div>

          <!-- Flight Time Field -->
          <div class="form-group col-sm-2">
            {{ Form::label('flight_time', 'Flight Time (hours & minutes)') }}

            <div class="input-group input-group-sm mb3">
              {{ Form::number('hours', null, [
                      'class' => 'form-control',
                      'placeholder' => 'hours',
                      'style' => 'width: 50%',
                      'min' => '0',
                  ]) }}

              {{ Form::number('minutes', null, [
                      'class' => 'form-control',
                      'placeholder' => 'minutes',
                      'style' => 'width: 50%',
                      'min' => '0',
                  ]) }}
            </div>

            <p class="text-danger">{{ $errors->first('hours') }}</p>
            <p class="text-danger">{{ $errors->first('minutes') }}</p>

          </div>
        </div>

        {{-- NEXT ROW --}}

        <div class="row">
          <div class="form-group col-sm-4">
            {{ Form::label('pilot_pay', 'Pilot Pay:') }}
            {{ Form::text('pilot_pay', null, ['class' => 'form-control']) }}
            <p class="text-danger">{{ $errors->first('pilot_pay') }}</p>
            @component('admin.components.info')
              Fill this in to pay a pilot a fixed amount for this flight.
            @endcomponent
          </div>

          <div class="form-group col-sm-4">
            {{ Form::label('load_factor', 'Load Factor:') }}
            {{ Form::number('load_factor', null, ['class' => 'form-control', 'min' => 0, 'max' => 100]) }}
            <p class="text-danger">{{ $errors->first('load_factor') }}</p>
            @component('admin.components.info')
              Percentage value for pax/cargo load, leave blank to use the default value.
            @endcomponent
          </div>

          <div class="form-group col-sm-4">
            {{ Form::label('load_factor_variance', 'Load Factor Variance:') }}
            {{ Form::number('load_factor_variance', null, ['class' => 'form-control', 'min' => 0, 'max' => 100]) }}
            <p class="text-danger">{{ $errors->first('load_factor_variance') }}</p>
            @component('admin.components.info')
              Percentage of how much the load can vary (+/-), leave blank to use the default value.
            @endcomponent
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-lg-12">
    <div class="form-container">
      <h6><i class="fas fa-map"></i>
        &nbsp;Route
      </h6>
      <div class="form-container-body">
        <div class="row">
          <div class="form-group col-sm-6">
            {{ Form::label('dpt_airport_id', 'Departure Airport:') }}&nbsp;<span
              class="required">*</span>
            {{ Form::select('dpt_airport_id', $airports, null , [
                    'id'    => 'dpt_airport_id',
                    'class' => 'form-control airport_search'
                ]) }}
            <p class="text-danger">{{ $errors->first('dpt_airport_id') }}</p>
          </div>

          <!-- Arr Airport Id Field -->
          <div class="form-group col-sm-6">
            {{ Form::label('arr_airport_id', 'Arrival Airport:') }}&nbsp;<span
              class="required">*</span>
            {{ Form::select('arr_airport_id', $airports, null , [
                    'id'    => 'arr_airport_id',
                    'class' => 'form-control airport_search'
                ]) }}
            <p class="text-danger">{{ $errors->first('arr_airport_id') }}</p>
          </div>

        </div>
        <div class="row">
          <!-- Route Field -->
          <div class="form-group col-sm-12">
            {{ Form::label('route', 'Route:') }}
            {{ Form::textarea('route', null, [
                'class' => 'form-control input-text',
                'style' => 'padding: 10px',
            ]) }}
            <p class="text-danger">{{ $errors->first('route') }}</p>
          </div>
        </div>
        <div class="row">
          <!-- Alt Airport Id Field -->
          <div class="form-group col-sm-4">
            {{ Form::label('alt_airport_id', 'Alt Airport:') }}
            {{ Form::select('alt_airport_id', $alt_airports, null , ['class' => 'form-control airport_search']) }}
            <p class="text-danger">{{ $errors->first('alt_airport_id') }}</p>
          </div>

          <div class="form-group col-sm-4">
            {{ Form::label('level', 'Flight Level:') }}
            {{ Form::text('level', null, ['class' => 'form-control']) }}
            <p class="text-danger">{{ $errors->first('level') }}</p>
          </div>

          <div class="form-group col-sm-4">
            {{ Form::label('distance', 'Distance:') }} <span class="description small">in nautical miles</span>
            <a href="#" class="airport_distance_lookup">Calculate</a>
            @if(isset($flight->distance))
              {{ Form::text('distance', $flight->distance['nmi'], null, ['id' => 'distance', 'class' => 'form-control']) }}
            @else
              {{ Form::text('distance', null, ['id' => 'distance', 'class' => 'form-control']) }}
            @endif
            <p class="text-danger">{{ $errors->first('distance') }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-12">
    <div class="form-container">
      <h6><i class="fas fa-clock"></i>
        &nbsp;Scheduling
      </h6>
      <div class="form-container-body">
        <div class="row">
          <div class="col-sm-4">
            {{ Form::label('start_date', 'Start Date') }}
            <span class="description small">optional</span>
            {{ Form::text('start_date', null, ['id' => 'start_date', 'class' => 'form-control', 'placeholder'=>'2021-03-25']) }}
          </div>

          <div class="col-sm-4">
            {{ Form::label('end_date', 'End Date') }}
            <span class="description small">optional</span>
            {{ Form::text('end_date', null, ['id' => 'end_date', 'class' => 'form-control', 'placeholder'=>'2021-06-30']) }}
          </div>

          <div class="form-group col-sm-4">
            {{ Form::label('days', 'Days of Week') }}
            <span class="description small">optional</span>
            <select id="days_of_week" name="days[]" multiple="multiple" size="7" style="width: 100%;">
              <option value="">Select Days</option>
              <option value="{{\App\Models\Enums\Days::MONDAY}}"
                {{in_mask($days, \App\Models\Enums\Days::MONDAY) ? 'selected':'' }}>
                @lang('common.days.mon')
              </option>
              <option value="{{\App\Models\Enums\Days::TUESDAY}}"
                {{in_mask($days, \App\Models\Enums\Days::TUESDAY) ? 'selected':'' }}>
                @lang('common.days.tues')
              </option>
              <option value="{{\App\Models\Enums\Days::WEDNESDAY}}"
                {{in_mask($days, \App\Models\Enums\Days::WEDNESDAY) ? 'selected':'' }}>
                @lang('common.days.wed')
              </option>
              <option value="{{\App\Models\Enums\Days::THURSDAY}}"
                {{in_mask($days, \App\Models\Enums\Days::THURSDAY) ? 'selected':'' }}>
                @lang('common.days.thurs')
              </option>
              <option value="{{\App\Models\Enums\Days::FRIDAY}}"
                {{in_mask($days, \App\Models\Enums\Days::FRIDAY) ? 'selected':'' }}>
                @lang('common.days.fri')
              </option>
              <option value="{{\App\Models\Enums\Days::SATURDAY}}"
                {{in_mask($days, \App\Models\Enums\Days::SATURDAY) ? 'selected':'false' }}>
                @lang('common.days.sat')
              </option>
              <option value="{{\App\Models\Enums\Days::SUNDAY}}"
                {{in_mask($days, \App\Models\Enums\Days::SUNDAY) ? 'selected':'false' }}>
                @lang('common.days.sun')
              </option>
            </select>
          </div>
        </div>

        <div class="row">
          <div class="form-group col-sm-4">
            {{ Form::label('dpt_time', 'Departure Time:') }}
            {{ Form::text('dpt_time', null, ['class' => 'form-control']) }}
            <p class="text-danger">{{ $errors->first('dpt_time') }}</p>
          </div>

          <div class="form-group col-sm-4">
            {{ Form::label('arr_time', 'Arrival Time:') }}
            {{ Form::text('arr_time', null, ['class' => 'form-control']) }}
            <p class="text-danger">{{ $errors->first('arr_time') }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-lg-12">
    <div class="form-container">
      <h6><i class="fas fa-sticky-note"></i>
        &nbsp;Remarks
      </h6>
      <div class="form-container-body">
        <div class="row">
          <div class="form-group col-sm-12">
            {{ Form::textarea('notes', null, [
                'id'    => 'editor',
                'class' => 'editor',
                'style' => 'padding: 5px',
            ]) }}
            <p class="text-danger">{{ $errors->first('notes') }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <!-- Active Field -->
  <div class="col-sm-3">
    <div class="checkbox">
      <label class="checkbox-inline">
        {{ Form::label('active', 'Active:') }}
        <input name="active" type="hidden" value="0" />
        {{ Form::checkbox('active') }}
      </label>
    </div>
  </div>
  <!-- Visible Field -->
  <div class="col-sm-3">
    <div class="checkbox">
      <label class="checkbox-inline">
        {{ Form::label('visible', 'Visible:') }}
        <input name="visible" type="hidden" value="0" />
        {{ Form::checkbox('visible') }}
      </label>
    </div>
  </div>
  <div class="col-6">
    <div class="text-right">
      {{ Form::button('Save', ['type' => 'submit', 'class' => 'btn btn-info']) }}
    </div>
  </div>
</div>
@section('scripts')
  @parent
  <script src="{{ public_asset('assets/vendor/ckeditor4/ckeditor.js') }}"></script>
  <script>
    $(document).ready(function () { CKEDITOR.replace('editor'); });
  </script>
@endsection
