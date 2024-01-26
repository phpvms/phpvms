@extends('admin.app')
@section('title', 'Invites')

@section('actions')
  <li><a href="{{ route('admin.invites.create') }}"><i class="ti-plus"></i>Add Invite</a></li>
@endsection

@section('content')
  <div class="card border-blue-bottom">
    <div class="content">
      @include('admin.invites.table')
    </div>
  </div>
@endsection

