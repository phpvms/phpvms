@extends('system.installer.app')
@section('title', 'Database Setup Completed')
@section('content')
  <div style="align-content: center;">
    <form method="get" action="{{ route('installer.step3') }}">
      @csrf

    <pre class="lang-sh">
        <code class="lang-sh">
        {{--<code class="language-bash">--}}
          {{ $console_output }}
        </code>
    </pre>

    <p style="text-align: right">
      <button type="submit" class="btn btn-success">Continue >></button>
    </p>
    </form>
  </div>
@endsection
