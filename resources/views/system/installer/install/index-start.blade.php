@extends('system.installer.app')
@section('title', 'Install phpVMS')

@section('content')
  <h3 class="text-center">Click on <b>Start</b> to Continue</h3>
  <form method="post" action="{{ route('installer.step1post') }}">
    @csrf
    <p style="text-align: right">
      <button type="submit" class="btn btn-success">Start >></button>
    </p>
  </form>
@endsection
