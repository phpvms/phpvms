<div class="row">
  <div class="form-group col-sm-4">
    {{ Form::label('icao', 'ICAO:') }}
    {{ Form::text('icao', null, ['class' => 'form-control']) }}
    <p class="text-danger">{{ $errors->first('icao') }}</p>
  </div>
  <div class="form-group col-sm-4">
    {{ Form::label('name', 'Name:') }}
    {{ Form::text('name', null, ['class' => 'form-control']) }}
    <p class="text-danger">{{ $errors->first('name') }}</p>
  </div>
  <div class="form-group col-sm-4">
    {{ Form::label('airframe_id', 'SB Airframe ID:') }}
    {{ Form::text('airframe_id', null, ['class' => 'form-control']) }}
    <p class="text-danger">{{ $errors->first('airframe_id') }}</p>
  </div>
</div>
<div class="row">
  <div class="col-sm-12">
    <div class="text-right">
      {{ Form::hidden('source', \App\Models\Enums\AirframeSource::INTERNAL) }}
      {{ Form::button('Save', ['type' => 'submit', 'class' => 'btn btn-success']) }}
    </div>
  </div>
</div>
