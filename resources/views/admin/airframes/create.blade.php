@extends('admin.app')
@section('title', 'Add SimBrief Airframe')
@section('content')
  <div class="card border-blue-bottom">
    <div class="content">
      {{ Form::open(['route' => 'admin.airframes.store', 'class' => 'add_airframe', 'method'=>'POST', 'autocomplete' => false]) }}
      @include('admin.airframes.fields')
      {{ Form::close() }}
    </div>
  </div>
@endsection
