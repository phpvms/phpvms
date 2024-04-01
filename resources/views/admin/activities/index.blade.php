@extends('admin.app')
@section('title', 'Activities')

@section('content')
  <div class="card border-blue-bottom">
    <div class="content">
      @include('admin.activities.table')
    </div>
  </div>

  <div class="row">
    <div class="col-12 text-center">
      {{ $activities->withQueryString()->links('admin.pagination.default') }}
    </div>
  </div>
@endsection
