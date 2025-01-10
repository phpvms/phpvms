@extends('app')
@section('title', __('auth.registrationsubmitted'))

@section('content')
  <div class="container registered-page">
    <h3>@lang('auth.registrationconfirmation')</h3>
    <p>
      @lang('auth.confirmationmessage')
    </p>
  </div>
@endsection
