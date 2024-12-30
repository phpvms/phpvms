@extends('app')
@section('title', __('auth.registrationdenied'))

@section('content')
  <div class="row justify-content-center">
    <div class="col-md-12 text-center">
      <div class="d-flex align-items-center justify-content-center">
        <div class="title mb-4">
          <h2 class="description">
            @lang('auth.deniedmessage')
          </h2>
        </div>
      </div>
    </div>
  </div>
@endsection
