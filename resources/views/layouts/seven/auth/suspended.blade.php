@extends('app')
@section('title', __('auth.accountsuspended'))

@section('content')
  <div class="row">
    <div class="col-md-12 " style="text-align: center;">
      <div class="flex-center position-ref full-height">
        <div class="title m-b-md">
          <h2 class="description">
            @lang('auth.suspendedmessage')
          </h2>
        </div>
      </div>
    </div>
  </div>
@endsection
