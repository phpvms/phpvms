@extends('app')
@section('title', $airport->full_name)

@section('content')
  <div class="row" style="margin-bottom: 30px;">
    <div class="col-12">
      <h2>
        {{ $airport->full_name }}
        @if(filled($airport->elevation))
          <span class="float-right">{{'| '.$airport->elevation.'ft'}}</span>
        @endif
      </h2>
    </div>

    {{-- Show the weather widget in one column --}}
    <div class="col-5">
      {{ Widget::Weather(['icao' => $airport->icao]) }}
    </div>

    {{-- Show the airspace map in the other column --}}
    <div class="col-7">
      {{ Widget::AirspaceMap(['width' => '100%', 'height' => '400px', 'lat' => $airport->lat, 'lon' => $airport->lon]) }}
      @if(filled($airport->notes))
        <hr>
        {!! $airport->notes !!}
      @endif
    </div>
  </div>
  <div class="row">
    {{-- There are files uploaded and a user is logged in--}}
    @if(count($airport->files) > 0 && Auth::check())
      <div class="col-12">
        <h3>{{ trans_choice('common.download', 2) }}</h3>
        @include('downloads.table', ['files' => $airport->files])
      </div>
    @endif
  </div>

  <div class="row">
    <div class="col-6">
      <h5>@lang('flights.inbound')</h5>
      @if(!$inbound_flights)
        <div class="jumbotron text-center">
          @lang('flights.none')
        </div>
      @else
        <table class="table table-striped">
          <thead>
          <tr>
            <th class="text-left">@lang('airports.ident')</th>
            <th class="text-left">@lang('airports.departure')</th>
            <th>@lang('flights.dep')</th>
            <th>@lang('flights.arr')</th>
          </tr>
          </thead>
          @foreach($inbound_flights as $flight)
            <tr>
              <td class="text-left">
                <a href="{{ route('frontend.flights.show', [$flight->id]) }}">
                  {{ $flight->ident }}
                </a>
              </td>
              <td class="text-left">{{ optional($flight->dpt_airport)->name }}
                (<a href="{{route('frontend.airports.show',
                         ['id' => $flight->dpt_airport_id])}}">{{$flight->dpt_airport_id}}</a>)
              </td>
              <td>{{ $flight->dpt_time }}</td>
              <td>{{ $flight->arr_time }}</td>
            </tr>
          @endforeach
        </table>
      @endif
    </div>

    <div class="col-6">
      <h5>@lang('flights.outbound')</h5>
      @if(!$outbound_flights)
        <div class="jumbotron text-center">
          @lang('flights.none')
        </div>
      @else
        <table class="table table-striped">
          <thead>
          <tr>
            <th class="text-left">@lang('airports.ident')</th>
            <th class="text-left">@lang('airports.arrival')</th>
            <th>@lang('flights.dep')</th>
            <th>@lang('flights.arr')</th>
          </tr>
          </thead>
          @foreach($outbound_flights as $flight)
            <tr>
              <td class="text-left">
                <a href="{{ route('frontend.flights.show', [$flight->id]) }}">
                  {{ $flight->ident }}
                </a>
              </td>
              <td class="text-left">{{ $flight->arr_airport->name }}
                (<a href="{{route('frontend.airports.show',
                         ['id'=>$flight->arr_airport->icao])}}">{{$flight->arr_airport->icao}}</a>)
              </td>
              <td>{{ $flight->dpt_time }}</td>
              <td>{{ $flight->arr_time }}</td>
            </tr>
          @endforeach
        </table>
      @endif
    </div>
  </div>
@endsection
