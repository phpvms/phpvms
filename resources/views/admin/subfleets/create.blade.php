@extends('admin.app')
@section('title', 'Add Subfleet')
@section('content')
  <div class="card border-blue-bottom">
    <div class="content">
      @if(!filled($airlines))
        <p class="text-center">
          You must add an airline before you can add a subfleet!
        </p>
      @else
        {{ Form::open(['route' => 'admin.subfleets.store', 'autocomplete' => false]) }}
        @include('admin.subfleets.fields')
        {{ Form::close() }}
      @endif
    </div>
  </div>
@endsection
@include('admin.subfleets.script')

