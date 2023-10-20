@extends('admin.app')
@section('title', 'Aircraft')

@section('actions')
  @if(request()->get('subfleet'))
    <li>
      <a href="{{ route('admin.aircraft.export') }}{{ '?subfleet='.request()->get('subfleet') }}">
        <i class="ti-plus"></i>
        Export to CSV (Selected Subfleet Only)
      </a>
    </li>
  @endif
  <li>
    <a href="{{ route('admin.aircraft.export') }}">
      <i class="ti-plus"></i>
      Export to CSV
    </a>
  </li>
  <li>
    <a href="{{ route('admin.aircraft.import') }}">
      <i class="ti-plus"></i>
      Import from CSV
    </a>
  </li>
  <li>
    <a href="{{ route('admin.aircraft.create') }}?subfleet={{$subfleet_id}}">
      <i class="ti-plus"></i>
      New Aircraft
    </a>
  </li>
@endsection

@section('content')
  <div class="card border-blue-bottom">
    <div class="content">
      @include('admin.aircraft.table')
      @if(filled($trashed))
        <hr>
        <div class="row mb-2 text-center"><b>Trashed Aircraft Records</b></div>
        @include('admin.aircraft.trash_table')
      @endif
    </div>
  </div>
@endsection
