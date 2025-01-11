@extends('admin.app')
@section('title', 'Edit '.$airframe->name)
@section('content')
  <div class="card border-blue-bottom">
    <div class="content">
      {{ Form::model($airframe, ['route' => ['admin.airframes.update', $airframe->id], 'method' => 'patch', 'autocomplete' => false]) }}
      @include('admin.airframes.fields')
      {{ Form::close() }}
    </div>
  </div>
@endsection
