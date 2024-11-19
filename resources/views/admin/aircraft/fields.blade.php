<div class="row">
  <div class="col-sm-12">
    <div class="form-container">
      <h6><i class="fas fa-clock"></i>&nbsp;Subfleet and Status</h6>
      <div class="form-container-body row">
        <div class="form-group col-sm-3">
          {{ Form::label('subfleet_id', 'Subfleet:') }}
          {{ Form::select('subfleet_id', $subfleets, $subfleet_id ?? null, [
              'class' => 'form-control select2',
              'placeholder' => 'Select Subfleet'
              ])
          }}
          <p class="text-danger">{{ $errors->first('subfleet_id') }}</p>
        </div>

        <div class="form-group col-sm-3">
          {{ Form::label('status', 'Status:') }}
          {{ Form::select('status', $statuses, null, ['class' => 'form-control select2', 'placeholder' => 'Select Status']) }}
          <p class="text-danger">{{ $errors->first('subfleet_id') }}</p>
        </div>

        <div class="form-group col-sm-3">
          {{ Form::label('hub_id', 'Home:') }}
          {{ Form::select('hub_id', $hubs, null, ['class' => 'form-control airport_search']) }}
          <p class="text-danger">{{ $errors->first('hub_id') }}</p>
        </div>

        <div class="form-group col-sm-3">
          {{ Form::label('airport_id', 'Location:') }}
          {{ Form::select('airport_id', $airports, null, ['class' => 'form-control airport_search']) }}
          <p class="text-danger">{{ $errors->first('airport_id') }}</p>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-12">
    <div class="form-container">
      <h6>
        <i class="fas fa-plane"></i>&nbsp;Aircraft Information
        <span style="float:right">
          View list of
          <a href="https://en.wikipedia.org/wiki/List_of_ICAO_aircraft_type_designators" target="_blank">IATA and ICAO Type Designators</a>
        </span>
      </h6>
      <div class="form-container-body">
        <div class="row">
          <div class="form-group col-sm-3">
            {{ Form::label('name', 'Name:') }}&nbsp;<span class="required">*</span>
            {{ Form::text('name', null, ['class' => 'form-control']) }}
            <p class="text-danger">{{ $errors->first('name') }}</p>
          </div>
          <div class="form-group col-sm-3">
            {{ Form::label('registration', 'Registration:') }}&nbsp;<span class="required">*</span>
            {{ Form::text('registration', null, ['class' => 'form-control']) }}
            <p class="text-danger">{{ $errors->first('registration') }}</p>
          </div>
          <div class="form-group col-sm-3">
            {{ Form::label('fin', 'FIN:') }}
            {{ Form::text('fin', null, ['class' => 'form-control']) }}
            <p class="text-danger">{{ $errors->first('fin') }}</p>
          </div>
          <div class="form-group col-sm-3">
            {{ Form::label('selcal', 'SELCAL:') }}
            {{ Form::text('selcal', null, ['class' => 'form-control']) }}
            <p class="text-danger">{{ $errors->first('selcal') }}</p>
          </div>
        </div>
        <div class="row">
          <div class="form-group col-sm-3">
            {{ Form::label('iata', 'IATA:') }}
            {{ Form::text('iata', null, ['class' => 'form-control']) }}
            <p class="text-danger">{{ $errors->first('iata') }}</p>
          </div>
          <div class="form-group col-sm-3">
            {{ Form::label('icao', 'ICAO:') }}
            {{ Form::text('icao', null, ['class' => 'form-control']) }}
            <p class="text-danger">{{ $errors->first('icao') }}</p>
          </div>
          <div class="form-group col-sm-3">
            {{ Form::label('simbrief_type', 'SimBrief Type:') }}
            {{ Form::text('simbrief_type', null, ['class' => 'form-control']) }}
            <p class="text-danger">{{ $errors->first('simbrief_type') }}</p>
          </div>
          <div class="form-group col-sm-3">
            {{ Form::label('hex_code', 'Hex Code:') }}
            {{ Form::text('hex_code', null, ['class' => 'form-control']) }}
            <p class="text-danger">{{ $errors->first('hex_code') }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-12">
    <div class="form-container">
      <h6><i class="fas fa-plane"></i>&nbsp;Certified Weights ({{ setting('units.weight') }})</h6>
      <div class="form-container-body">
        <div class="row">
          <div class="form-group col-sm-3">
            {{ Form::label('dow', 'Dry Operating Weight (DOW/OEW):') }}
            <div class="row">
              <div class="col-sm-12">
                <input class="form-control" type="number" name="dow" value="{{ $aircraft->dow->local(0) ?? null }}" step="1" />
                <p class="text-danger">{{ $errors->first('dow') }}</p>
              </div>
            </div>
          </div>
          <div class="form-group col-sm-3">
            {{ Form::label('zfw', 'Max Zero Fuel Weight (MZFW):') }}
            <div class="row">
              <div class="col-sm-12">
                <input class="form-control" type="number" name="zfw" value="{{ $aircraft->zfw->local(0) ?? null }}" step="1" />
                <p class="text-danger">{{ $errors->first('zfw') }}</p>
              </div>
            </div>            
          </div>
          <div class="form-group col-sm-3">
            {{ Form::label('mtow', 'Max Takeoff Weight (MTOW):') }}
            <div class="row">
              <div class="col-sm-12">
                <input class="form-control" type="number" name="mtow" value="{{ $aircraft->mtow->local(0) ?? null }}" step="1" />
                <p class="text-danger">{{ $errors->first('mtow') }}</p>
              </div>
            </div> 
          </div>
          <div class="form-group col-sm-3">
            {{ Form::label('mlw', 'Max Landing Weight (MLW):') }}
            <div class="row">
              <div class="col-sm-12">
                <input class="form-control" type="number" name="mlw" value="{{ $aircraft->mlw->local(0) ?? null }}" step="1" />
                <p class="text-danger">{{ $errors->first('mlw') }}</p>
              </div>
            </div> 
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <!-- Submit Field -->
  <div class="form-group col-sm-12">
    <div class="pull-right">
      {{ Form::button('Save', ['type' => 'submit', 'class' => 'btn btn-success']) }}
    </div>
  </div>
</div>
