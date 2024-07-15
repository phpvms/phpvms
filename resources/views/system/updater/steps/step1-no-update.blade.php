@extends('system.updater.app')
@section('title', 'Update phpVMS')

@section('content')
  <h2>phpvms updater</h2>
  <p>It seems like you're up to date!</p>
  <form method="get" action="{{ route('update.complete') }}">
    @csrf

  <p style="text-align: right">
    <button type="submit" class="btn btn-success">Complete >></button>
  </p>
  </form>
@endsection
