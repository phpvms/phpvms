@extends('system.installer.app')
@section('title', 'Installation Completed!')

@section('content')
    <div style="align-content: center;">
        <form method="get" action="{{ route('installer.complete') }}">
            @csrf

            <h4>Installer Completed!</h4>

            <p>Edit the <span class="code">.env</span> to fill in some additional settings.</p>
            <p>Click the button to proceed to the login screen!</p>

            <p style="text-align: right">
                <button type="submit" class="btn btn-success">Continue to Log-In >></button>
            </p>
        </form>
    </div>
@endsection
