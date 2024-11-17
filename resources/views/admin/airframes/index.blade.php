@extends('admin.app')
@section('title', 'SimBrief Airframes')
@section('actions')
  <li>
    <a href="{{ route('admin.airframes.create') }}">
      <i class="ti-plus"></i>
      Add New
    </a>
  </li>
@endsection

@section('content')
  <div class="card border-blue-bottom">
    <div class="content">
      @include('admin.airframes.table')
    </div>
  </div>
@endsection
