@if($aircraft && $aircraft->subfleet->fares->count() > 0)
  <div class="card mb-3">
    <div class="card-header bg-primary text-white">
      {{ trans_choice('pireps.fare', 2) }}
    </div>
    <div class="card-body">
      @foreach($aircraft->subfleet->fares as $fare)
        <div class="row mb-3">
          <div class="col">
            <label for="fare_{{ $fare->id }}">{{ $fare->name.' ('. \App\Models\Enums\FareType::label($fare->type).', code '.$fare->code.')' }}</label>
            <div class="input-group">
              <input type="number" name="fare_{{ $fare->id }}" id="fare_{{ $fare->id }}" class="form-control" min="0" value="{{ old('fare_'.$fare->id) }}">
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
@endif
