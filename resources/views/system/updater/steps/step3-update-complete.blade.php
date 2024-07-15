@extends('system.updater.app')
@section('title', 'Update Completed')

@section('content')
  <h2>phpvms updater</h2>
  <p>Update completed!.</p>

  <form method="get" action="{{ route('update.complete') }}">
    @csrf
  <p style="text-align: right">
    <button type="submit" class="btn btn-success">Finish >></button>
  </p>
  </form>
@endsection
