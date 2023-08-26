@extends('admin.app')
@section('title', 'Add Flight')
@section('content')
  <div class="card border-blue-bottom">
    <div class="content">
      {{ Form::model($flight, ['route' => ['admin.flights.store', 'autocomplete' => false]]) }}
      @include('admin.flights.fields')
      {{ Form::close() }}
    </div>
  </div>
@endsection
@include('admin.flights.scripts')
