@extends('sample::layouts.admin')

@section('title', 'Sample')
@section('actions')
  <li>
    <a href="{{ url('/admin/sample/create') }}">
      <i class="ti-plus"></i>
      Add New</a>
  </li>
@endsection
@section('content')
  <div class="card border-blue-bottom">
    <div class="content">
      <div class="header"><h4 class="title">Admin Scaffold!</h4></div>
      <p>This view is loaded from module: {{ config('sample.name') }}</p>
    </div>
  </div>
@endsection
