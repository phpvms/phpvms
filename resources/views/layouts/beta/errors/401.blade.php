@extends('app')
@section('title', __('errors.401.title'))

@section('content')
  <div class="container registered-page">
    <h3>@lang('errors.401.title')</h3>
    <p>
      {!! str_replace(':link', config('app.url'), __('errors.401.message')).'<br />' !!}
    </p>
  </div>
@endsection
