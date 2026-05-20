@extends('app')
@section('title', __('auth.registration_submitted'))

@section('content')
  <div class="container registered-page">
    <h3>@lang('auth.registration_confirmation')</h3>
    <p>
      @lang('auth.confirmation_message')
    </p>
  </div>
@endsection
