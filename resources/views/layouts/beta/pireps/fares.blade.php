@if($aircraft)
  <div class="form-container">
    <h6><i class="fas fa-ellipsis-h"></i>
      &nbsp;{{ trans_choice('pireps.fare', 2) }}
    </h6>
    <div class="form-container-body">
      @foreach($aircraft->subfleet->fares as $fare)
        <div class="row">
          <div class="col">
            <label for="fare_{{ $fare->id }}">{{ $fare->name.' ('. \App\Models\Enums\FareType::label($fare->type).', code '.$fare->code.')' }}</label>
            <div class="input-group form-group">
              <input type="number" name="fare_{{ $fare->id }}" id="fare_{{ $fare->id }}" class="form-control" min="0" value="{{ old('fare_'.$fare->id) }}">
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
@endif
