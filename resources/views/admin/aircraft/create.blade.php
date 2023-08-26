@extends('admin.app')
@section('title', 'Add Aircraft')
@section('content')
  <div class="card border-blue-bottom">
    <div class="content">
      @if(!filled($subfleets))
        <p class="text-center">
          You must add a subfleet before you can add an aircraft!
        </p>
      @else
        {{ Form::open(['route' => 'admin.aircraft.store', 'autocomplete' => false]) }}
        @include('admin.aircraft.fields')
        {{ Form::close() }}
      @endif
    </div>
  </div>
@endsection
@include('admin.aircraft.script')
