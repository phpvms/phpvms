@extends('admin.app')
@section('title', 'Flights')

@section('actions')
  <li>
    <a href="{{ route('admin.flights.export') }}@if(request()->get('airline_id')){{ '?airline_id='.request()->get('airline_id') }}@endif">
      <i class="ti-plus"></i>
      Export to CSV @if(request()->get('airline_id')) (Selected Airline) @endif
    </a>
  </li>
  <li>
    <a href="{{ route('admin.flights.import') }}"><i class="ti-plus"></i>Import from CSV</a>
  </li>
  <li>
    <a href="{{ route('admin.flights.create') }}">
      <i class="ti-plus"></i>
      Add Flight</a>
  </li>
@endsection

@section('content')
  <div class="card">
    @include('admin.flights.search')
  </div>

  <div class="card border-blue-bottom">
    <div class="content">
      @include('admin.flights.table')
    </div>
  </div>

  <div class="row">
    <div class="col-12 text-center">
      {{ $flights->links('admin.pagination.default') }}
    </div>
  </div>
@endsection

@section('scripts')
  @include('admin.scripts.airport_search')
@endsection
