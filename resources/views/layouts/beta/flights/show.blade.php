@extends('app')
@section('title', trans_choice('common.flight', 1).' '.$flight->ident)

@section('content')
  <div class="row">
    <div class="col-8">
      <div class="row">
        <div class="col-12">
          <h2>
            {{ $flight->ident }}
            @if(filled($flight->callsign) && !setting('simbrief.callsign', true))
              {{ '| '. $flight->atc }}
            @endif
            @if ($acars_plugin && $bid)
              <a href="vmsacars:bid/{{$bid->id}}" class="btn btn-info btn-sm float-right">Load in vmsACARS</a>
            @elseif ($acars_plugin)
              <a href="vmsacars:flight/{{$flight->id}}" class="btn btn-info btn-sm float-right">Load in vmsACARS</a>
            @endif
          </h2>
        </div>
      </div>
      <div class="row">
        <div class="col-12">
          <table class="table">
            <tr>
              <td>@lang('common.departure')</td>
              <td>
                {{ optional($flight->dpt_airport)->name ?? $flight->dpt_airport_id }}
                (<a href="{{route('frontend.airports.show', [
                            'id' => $flight->dpt_airport_id
                            ])}}">{{$flight->dpt_airport_id}}</a>)
                @ {{ $flight->dpt_time }}
              </td>
            </tr>

            <tr>
              <td>@lang('common.arrival')</td>
              <td>
                {{ optional($flight->arr_airport)->name ?? $flight->arr_airport_id }}
                (<a href="{{route('frontend.airports.show', [
                            'id' => $flight->arr_airport_id
                            ])}}">{{$flight->arr_airport_id }}</a>)
                @ {{ $flight->arr_time }}</td>
            </tr>
            @if($flight->alt_airport_id)
              <tr>
                <td>@lang('flights.alternateairport')</td>
                <td>
                  {{ optional($flight->alt_airport)->name ?? $flight->alt_airport_id }}
                  (<a href="{{route('frontend.airports.show', [
                            'id' => $flight->alt_airport_id
                            ])}}">{{$flight->alt_airport_id}}</a>)
                </td>
              </tr>
            @endif
            @if(filled($flight->route))
              <tr>
                <td>@lang('flights.route')</td>
                <td>{{ $flight->route }}</td>
              </tr>
            @endif
            @if(filled($flight->callsign) && !setting('simbrief.callsign', true))
              <tr>
                <td>@lang('flights.callsign')</td>
                <td>{{ $flight->atc }}</td>
              </tr>
            @endif
            @if(filled($flight->notes))
              <tr>
                <td>{{ trans_choice('common.note', 2) }}</td>
                <td>{{ $flight->notes }}</td>
              </tr>
            @endif
          </table>
        </div>
      </div>
      <div class="row">
        <div class="col-12">
          @include('flights.map')
        </div>
      </div>
    </div>
    <div class="col-4">
      <h5>{{$flight->dpt_airport_id}} @lang('common.metar')</h5>
      {{ Widget::Weather([
          'icao' => $flight->dpt_airport_id,
        ]) }}
      <br/>
      <h5>{{$flight->arr_airport_id}} @lang('common.metar')</h5>
      {{ Widget::Weather([
          'icao' => $flight->arr_airport_id,
        ]) }}
      @if ($flight->alt_airport_id)
        <br/>
        <h5>{{$flight->alt_airport_id}} @lang('common.metar')</h5>
        {{ Widget::Weather([
            'icao' => $flight->alt_airport_id,
          ]) }}
      @endif
    </div>
  </div>
@endsection
