@extends('app')
@section('title', __('errors.503.title'))

@section('content')
  <div class="d-flex align-items-center justify-content-center">
    <div class="text-center">
      <h1 class="display-1 fw-bold">500</h1>
      <h3 class="fs-3">@lang('errors.503.title')</h3>
      <p class="lead">
        If you are a regular user, please inform website administrators
      </p>
      <div class="lead bg-light p-4 rounded shadow-sm">
        <h4 class="text-danger fw-bold mb-3">For Administrators:</h4>
        <ul class="list-unstyled">
          <li class="mb-2">
            <i class="bi bi-journal-code text-primary"></i> Check Laravel logs for error details
          </li>
          <li class="mb-2">
            <i class="bi bi-gear text-primary"></i> Enable <code>APP_DEBUG</code> in the environment file and revisit this page
          </li>
        </ul>
        <p>Once you have more information:</p>
        <ul class="list-unstyled">
          <li class="mb-2">
            <i class="bi bi-chat-dots text-primary"></i> Ask for help with all necessary details
          </li>
          <li class="mb-2">
            <i class="bi bi-tools text-primary"></i> Attempt to resolve the issue yourself if possible
          </li>
        </ul>
        <p class="text-danger fw-bold mt-4">
          <i class="bi bi-exclamation-triangle-fill"></i> Sharing a screenshot of this page won't help. Include detailed information instead.
        </p>

        <a href="https://docs.phpvms.net/help" class="btn btn-danger">phpVMS Docs | Getting Help</a>
        <a href="{{ route('frontend.home') }}" class="btn btn-primary">Go Home</a>
        <a href="{{ url()->previous() }}" class="btn btn-primary">Go Back</a>
      </div>
    </div>
  </div>
@endsection
