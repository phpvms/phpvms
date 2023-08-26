<div class="content">
  {{ Form::open(['route' => 'admin.flights.index', 'method' => 'GET', 'class'=>'form-group']) }}
    <div class="row">
      <div class="form-group col-sm-2">
        {{ Form::label('airline_id', 'Airline:') }}
        {{ Form::select('airline_id', $airlines, null , ['class' => 'form-control select2']) }}
      </div>
      <div class="form-group input-group-sm col-sm-2">
        {{ Form::label('flight_number', 'Flight Number:') }}
        {{ Form::text('flight_number', null, ['class' => 'form-control']) }}
      </div>
      <div class="form-group col-sm-3">
        {{ Form::label('dpt_airport_id', 'Departure:') }}
        {{ Form::select('dpt_airport_id', $airports, null , ['class' => 'form-control airport_search']) }}
      </div>
      <div class="form-group col-sm-3">
        {{ Form::label('arr_airport_id', 'Arrival:') }}
        {{ Form::select('arr_airport_id', $airports, null , ['class' => 'form-control airport_search']) }}
      </div>
      <div class="form-group col-sm-2 text-center">
        <br>
        {{ Form::submit('Find', ['class' => 'btn btn-primary']) }}
        <a href="{{ route('admin.flights.index') }}" class="btn btn-secondary ml-2">Clear</a>
      </div>
    </div>
  {{ Form::close() }}
</div>
