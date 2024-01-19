@extends('system.installer.app')

@section('content')
  <h2>phpVMS already installed!</h2>
  <p>phpVMS has already been installed! You can use it right now.</p>
  {{ Form::open(['url' => '/', 'method' => 'get']) }}
  <p style="text-align: right">
    {{ Form::submit('Go to your site >>', ['class' => 'btn btn-success']) }}
  </p>
  {{ Form::close() }}
@endsection
