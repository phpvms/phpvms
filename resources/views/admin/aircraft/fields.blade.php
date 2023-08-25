<div class="row">
  <div class="col-sm-12">
    <div class="form-container">
      <h6><i class="fas fa-clock"></i>
        &nbsp;Subfleet and Status
      </h6>
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
          <span style="float:right">
              View list of
              <a href="https://en.wikipedia.org/wiki/List_of_ICAO_aircraft_type_designators"
                 target="_blank">IATA and ICAO Type Designators</a>
          </span>
        <i class="fas fa-plane"></i>&nbsp;Aircraft Information
      </h6>
      <div class="form-container-body">

        <div class="row">
          <div class="form-group col-sm-12">
            {{ Form::label('name', 'Name:') }}&nbsp;<span class="required">*</span>
            {{ Form::text('name', null, ['class' => 'form-control']) }}
            <p class="text-danger">{{ $errors->first('name') }}</p>
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
            {{ Form::label('registration', 'Registration:') }}
            {{ Form::text('registration', null, ['class' => 'form-control']) }}
            <p class="text-danger">{{ $errors->first('registration') }}</p>
          </div>

          <div class="form-group col-sm-3">
            {{ Form::label('fin', 'FIN:') }}
            {{ Form::text('fin', null, ['class' => 'form-control']) }}
            <p class="text-danger">{{ $errors->first('fin') }}</p>
          </div>
        </div>

        <div class="row">
          <div class="form-group col-sm-6">
            {{ Form::label('mtow', 'Max Takeoff Weight (MTOW):') }}
            {{ Form::text('mtow', null, ['class' => 'form-control']) }}
            <p class="text-danger">{{ $errors->first('mtow') }}</p>
          </div>
          <div class="form-group col-sm-6">
            {{ Form::label('zfw', 'Zero Fuel Weight (ZFW):') }}
            {{ Form::text('zfw', null, ['class' => 'form-control']) }}
            <p class="text-danger">{{ $errors->first('zfw') }}</p>
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
