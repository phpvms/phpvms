@extends('system.installer.app')
@section('title', 'Installation Completed!')

@section('content')
  <div style="align-content: center;">
    {{ Form::open(['route' => 'installer.complete', 'method' => 'GET']) }}

    <h4>Installer Completed!</h4>

    <p>Edit the <span class="code">.env</span> file to fill in some additional settings.</p>
    <p>Click the button to proceed to the login screen!</p>

    <p style="text-align: right">
      {{ Form::submit('Install Complete! Continue to Log-In >>',
                       ['class' => 'btn btn-success'])
      }}
    </p>
    {{ Form::close() }}
  </div>
@endsection
