@extends('system.updater.app')
@section('title', 'Update phpVMS')

@section('content')
  <h2>phpvms updater</h2>
  <p>Click run to complete the update!.</p>
  <form method="post" action="{{ route('update.run_migrations') }}">
    @csrf
  <p style="text-align: right">
    <button type="submit" class="btn btn-success">Run >></button>
  </p>
  </form>
@endsection
