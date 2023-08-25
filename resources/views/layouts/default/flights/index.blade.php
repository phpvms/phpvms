@extends('app')
@section('title', trans_choice('common.flight', 2))

@section('content')
  <div class="row">
    @include('flash::message')
    <div class="col-md-9">
      <h2>{{ trans_choice('common.flight', 2) }}</h2>
      @include('flights.table')
    </div>
    <div class="col-md-3">
      @include('flights.nav')
      @include('flights.search')
    </div>
  </div>
  <div class="row">
    <div class="col-12 text-center">
      {{ $flights->withQueryString()->links('pagination.default') }}
    </div>
  </div>
  @if (setting('bids.block_aircraft', false))
    @include('flights.bids_aircraft')
  @endif
@endsection

@include('flights.scripts')

