@extends('admin.app')
@section('title', 'Add Invite')
@section('content')
  <div class="card border-blue-bottom">
    <div class="content">
      {{ Form::open(['route' => 'admin.invites.store', 'autofill' => false]) }}
      @include('admin.invites.fields')
      {{ Form::close() }}
    </div>
  </div>
@endsection
