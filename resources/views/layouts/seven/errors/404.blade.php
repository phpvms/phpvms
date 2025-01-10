@extends('app')
@section('title', __('errors.404.title'))

@section('content')
  <div class="d-flex align-items-center justify-content-center">
    <div class="text-center">
      <h1 class="display-1 fw-bold">404</h1>
      <h3 class="fs-3">@lang('errors.404.title')</h3>
      <p class="lead">
        Well, this is embarrassing, the page you requested does not exist.
      </p>
      <a href="{{ route('frontend.home') }}" class="btn btn-primary">Go Home</a>
      <a href="{{ url()->previous() }}" class="btn btn-primary">Go Back</a>
    </div>
  </div>
@endsection
