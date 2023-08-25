<div class="row">
  <div class="col-12">
    <div class="row">
      <div class="col-sm-12">
        @component('admin.components.info')
          Subfleets are aircraft groups. The "type" is a short name. Airlines always
          group aircraft together by feature, so 737s with winglets might have a type of
          "B.738-WL". You can create as many as you want, you need at least one, though.

          Read more about subfleets <a href="{{ docs_link('finances') }}" target="_new">here</a>.
        @endcomponent
      </div>
    </div>
    <div class="row">
      <div class="form-group col-sm-3">
        {{ Form::label('airline_id', 'Airline:') }}
        {{ Form::select('airline_id', $airlines, null , ['class' => 'form-control select2']) }}
        <p class="text-danger">{{ $errors->first('airline_id') }}</p>
      </div>

      <div class="form-group col-sm-3">
        {{ Form::label('hub_id', 'Home Base:') }}
        {{ Form::select('hub_id', $airports, null , ['class' => 'form-control airport_search', 'placeholder' => '']) }}
        <p class="text-danger">{{ $errors->first('hub_id') }}</p>
      </div>

      <div class="form-group col-sm-2">
        {{ Form::label('type', 'Type:') }}
        {{ Form::text('type', null, ['class' => 'form-control']) }}
        <p class="text-danger">{{ $errors->first('type') }}</p>
      </div>

      <div class="form-group col-sm-2">
        {{ Form::label('simbrief_type', 'SimBrief Type:') }}
        {{ Form::text('simbrief_type', null, ['class' => 'form-control']) }}
        <p class="text-danger">{{ $errors->first('simbrief_type') }}</p>
      </div>

      <div class="form-group col-sm-2">
        {{ Form::label('name', 'Name:') }}
        {{ Form::text('name', null, ['class' => 'form-control']) }}
        <p class="text-danger">{{ $errors->first('name') }}</p>
      </div>
    </div>
  </div>
</div>
<div class="row">

  <div class="form-group col-sm-3">
    {{ Form::label('cost_block_hour', 'Cost Per Hour:') }}
    {{ Form::number('cost_block_hour', null , ['class' => 'form-control', 'step' => '0.01']) }}
    <p class="text-danger">{{ $errors->first('cost_block_hour') }}</p>
  </div>

  <div class="form-group col-sm-3">
    {{ Form::label('cost_delay_minute', 'Cost Delay Per Minute:') }}
    {{ Form::number('cost_delay_minute', null , ['class' => 'form-control', 'step' => '0.01']) }}
    <p class="text-danger">{{ $errors->first('cost_delay_minute') }}</p>
  </div>

  <div class="form-group col-sm-3">
    {{ Form::label('fuel_type', 'Fuel Type:') }}
    {{ Form::select('fuel_type', $fuel_types, null , ['class' => 'form-control select2']) }}
    <p class="text-danger">{{ $errors->first('fuel_type') }}</p>
  </div>

  <div class="form-group col-sm-3">
    {{ Form::label('ground_handling_multiplier', 'Expense Multiplier:') }}
    {{ Form::text('ground_handling_multiplier', null, ['class' => 'form-control']) }}
    <p class="text-danger">{{ $errors->first('ground_handling_multiplier') }}</p>

    @component('admin.components.info')
      This is the multiplier for all expenses (inc GH costs) being applied to aircraft in this subfleet, as a percentage. Defaults to 100.
    @endcomponent
  </div>
</div>
<div class="row">

  <div class="form-group col-sm-12">
    <div class="pull-right">
      {{ Form::button('Save', ['type' => 'submit', 'class' => 'btn btn-success']) }}
      <a href="{{ route('admin.subfleets.index') }}" class="btn btn-default">Cancel</a>
    </div>
  </div>
</div>
