@extends('system.updater.app')
@section('title', 'Update Completed')
@section('content')
  <div style="align-content: center;">
    <form method="get" action="{{ route('update.complete') }}">
      @csrf

    <pre class="lang-sh">
        <code class="lang-sh">
            {{ $console_output }}
        </code>
    </pre>

    <p style="text-align: right">
      <button type="submit" class="btn btn-success">Complete >></button>
    </p>
    </form>
  </div>
@endsection
