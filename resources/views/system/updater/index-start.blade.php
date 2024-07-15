@extends('system.updater.app')
@section('title', 'Update phpVMS')

@section('content')
  <h2>phpvms updater</h2>
  <p>Press continue to check if there are any updates available.</p>
  <form method="post" action="{{ route('update.step1post') }}">
    @csrf
  <p style="text-align: right">
    <button type="submit" class="btn btn-success">Start >></button>
  </p>
  </form>
@endsection
