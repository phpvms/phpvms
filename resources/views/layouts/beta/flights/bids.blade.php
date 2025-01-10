@extends('app')
@section('title', __('flights.mybid'))

@section('content')
  <div class="row">
    @include('flash::message')
    <div class="col-md-12">
      <h2>{{ __('flights.mybid') }}</h2>
      @include('flights.table')
    </div>
  </div>
@endsection

@include('flights.scripts')

