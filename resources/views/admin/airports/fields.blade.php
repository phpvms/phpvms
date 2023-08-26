<div class="row">
  <div class="col-lg-12">
    <!-- Icao Field -->
    <div class="row">
      <div class="form-group col-sm-6">
        {{ Form::label('icao', 'ICAO:') }}&nbsp;<span class="required">*</span>
        <a href="#" class="airport_data_lookup">Lookup</a>
        {{ Form::text('icao', null, [
            'id' => 'airport_icao', 'class' => 'form-control'
            ]) }}
        <p class="text-danger">{{ $errors->first('icao') }}</p>
      </div>

      <div class="form-group col-sm-6">
        {{ Form::label('iata', 'IATA:') }}
        {{ Form::text('iata', null, ['class' => 'form-control']) }}
        <p class="text-danger">{{ $errors->first('iata') }}</p>
      </div>

    </div>

    <div class="row">
      <div class="form-group col-sm-4">
        {{ Form::label('name', 'Name:') }}&nbsp;<span class="required">*</span>
        {{ Form::text('name', null, ['class' => 'form-control']) }}
        <p class="text-danger">{{ $errors->first('name') }}</p>
      </div>
      <div class="form-group col-sm-4">
        {{ Form::label('lat', 'Latitude:') }}&nbsp;<span class="required">*</span>
        {{ Form::text('lat', null, ['class' => 'form-control', 'rv-value' => 'airport.lat']) }}
        <p class="text-danger">{{ $errors->first('lat') }}</p>
      </div>

      <div class="form-group col-sm-4">
        {{ Form::label('lon', 'Longitude:') }}&nbsp;<span class="required">*</span>
        {{ Form::text('lon', null, ['class' => 'form-control', 'rv-value' => 'airport.lon']) }}
        <p class="text-danger">{{ $errors->first('lon') }}</p>
      </div>
    </div>

    <div class="row">

      <div class="form-group col-sm-4">
        {{ Form::label('country', 'Country:') }}
        {{ Form::text('country', null, ['class' => 'form-control']) }}
        <p class="text-danger">{{ $errors->first('country') }}</p>
      </div>

      <div class="form-group col-sm-4">
        {{ Form::label('location', 'Location:') }}
        {{ Form::text('location', null, ['class' => 'form-control']) }}
        <p class="text-danger">{{ $errors->first('location') }}</p>
      </div>

      <div class="form-group col-sm-4">
        {{ Form::label('timezone', 'Timezone:') }}
        {{ Form::select('timezone', $timezones, null, ['id' => 'timezone', 'class' => 'select2']) }}
        <p class="text-danger">{{ $errors->first('timezone') }}</p>
      </div>
    </div>

    <div class="row">
      <div class="form-group col-sm-3">
        {{ Form::label('ground_handling_cost', 'Ground Handling Cost:') }}
        {{ Form::number('ground_handling_cost', null, ['class' => 'form-control', 'step' => '0.01']) }}
        <p class="text-danger">{{ $errors->first('ground_handling_cost') }}</p>

        @component('admin.components.info')
          This is the base rate per-flight. A multiplier for this rate can be
          set in the subfleet, so you can modulate those costs from there.
        @endcomponent
      </div>

      <div class="form-group col-md-3">
        {{ Form::label('fuel_jeta_cost', 'Jet A Fuel Cost:') }}
        {{ Form::number('fuel_jeta_cost', null, ['class' => 'form-control', 'step' => '0.01']) }}
        <p class="text-danger">{{ $errors->first('fuel_jeta_cost') }}</p>

        @component('admin.components.info')
          This is the cost per {{ config('phpvms.internal_units.fuel') }}
        @endcomponent
      </div>

      <div class="form-group col-md-3">
        {{ Form::label('fuel_100ll_cost', '100LL Fuel Cost:') }}
        {{ Form::number('fuel_100ll_cost', null, ['class' => 'form-control', 'step' => '0.01']) }}
        <p class="text-danger">{{ $errors->first('fuel_100ll_cost') }}</p>

        @component('admin.components.info')
          This is the cost per {{ config('phpvms.internal_units.fuel') }}
        @endcomponent
      </div>

      <div class="form-group col-md-3">
        {{ Form::label('fuel_mogas_cost', 'MOGAS Fuel Cost:') }}
        {{ Form::number('fuel_mogas_cost', null, ['class' => 'form-control', 'step' => '0.01']) }}
        <p class="text-danger">{{ $errors->first('fuel_mogas_cost') }}</p>

        @component('admin.components.info')
          This is the cost per {{ config('phpvms.internal_units.fuel') }}
        @endcomponent
      </div>
    </div>

    <div class="row">
      <div class="form-group col-md-12">
        {{ Form::label('notes', 'Remarks / Notes:') }}
        {{ Form::textarea('notes', null, ['id' => 'editor', 'class' => 'editor', 'style' => 'padding: 5px']) }}
      </div>
    </div>

    <div class="row">
      <div class="form-group col-sm-4">
        {{ Form::label('hub', 'Hub:') }}
        {{ Form::hidden('hub', 0)  }}
        {{ Form::checkbox('hub', null) }}
      </div>
      <!-- Submit Field -->
      <div class="form-group col-sm-8">
        <div class="text-right">
          {{ Form::button('Save', ['type' => 'submit', 'class' => 'btn btn-success']) }}
          <a href="{{ route('admin.airports.index') }}" class="btn btn-default">Cancel</a>
        </div>
      </div>
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
