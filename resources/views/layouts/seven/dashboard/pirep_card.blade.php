<div class="card-body" style="min-height: 0px">
  <div class="row">
    <div class="col-sm-10">
      <p>
        <a href="{{ route('frontend.pireps.show', [$pirep->id]) }}">{{ $pirep->ident }}</a>
        -
        {{ $pirep->dpt_airport->name }}
        (<a href="{{route('frontend.airports.show', [
                          'id' => $pirep->dpt_airport->icao
                          ])}}">{{$pirep->dpt_airport->icao}}</a>)
        <span class="description">to</span>
        {{ $pirep->arr_airport->name }}
        (<a href="{{route('frontend.airports.show', [
                          'id' => $pirep->arr_airport->icao
                          ])}}">{{$pirep->arr_airport->icao}}</a>)
      </p>
    </div>
    <div class="col-sm-2 float-right">
      <div class="col-sm-2 text-center">
          @if($pirep->state === PirepState::PENDING)
            <div class="badge bg-warning">
          @elseif($pirep->state === PirepState::ACCEPTED)
              <div class="badge bg-success">
          @elseif($pirep->state === PirepState::REJECTED)
              <div class="badge bg-danger">
          @else
             <div class="badge bg-info">
          @endif
            {{ PirepState::label($pirep->state) }}</div>
          <a href="{{ route('frontend.pireps.edit', [$pirep->id]) }}"
            class="btn btn-sm btn-info">@lang('common.edit')</a> 
      </div>    
    </div>
  </div>
</div>
