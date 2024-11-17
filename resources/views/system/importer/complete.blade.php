@extends('system.importer.app')
@section('title', 'Import Completed!')

@section('content')
    <div style="align-content: center;">
        <form method="get" action="{{ route('importer.complete') }}">
            @csrf
            <h4>Installer Completed!</h4>

            <p>Edit the <span class="code">config.php</span> to fill in some additional settings. </p>
            <p>Click the button to proceed to the login screen!</p>

            <p style="text-align: right">
                <button type="submit" class="btn btn-success">Import Complete! Continue to Log-In >></button>
            </p>
        </form>
    </div>
@endsection
