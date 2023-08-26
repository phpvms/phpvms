@extends('admin.app')

@section('title', "Edit $subfleet->name")
@section('content')
  <div class="card border-blue-bottom">
    <div class="content">
      {{ Form::model($subfleet, ['route' => ['admin.subfleets.update', $subfleet->id], 'method' => 'patch', 'autocomplete' => false]) }}
      @include('admin.subfleets.fields')
      {{ Form::close() }}
    </div>
  </div>

  <div class="card border-blue-bottom">
    <div class="content">
      @include('admin.subfleets.ranks')
    </div>
  </div>

  <div class="card border-blue-bottom">
    <div class="content">
      @include('admin.subfleets.type_ratings')
    </div>
  </div>

  <div class="card border-blue-bottom">
    <div class="content">
      @include('admin.subfleets.fares')
    </div>
  </div>

  <div class="card border-blue-bottom">
    <div class="content">
      @include('admin.subfleets.expenses')
    </div>
  </div>

  <div class="card border-blue-bottom">
    <div class="content">
      @include('admin.common.file_upload', ['model' => $subfleet])
    </div>
  </div>
@endsection
@include('admin.subfleets.script')

