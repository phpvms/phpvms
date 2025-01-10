@extends('app')
@section('title', __('errors.404.title'))

@section('content')
  <div class="container registered-page">
    <h3>@lang('errors.404.title')</h3>
    <p>
      {!! str_replace(':link', config('app.url'), __('errors.404.message')).'<br />' !!}
      {{ $exception->getMessage() }}
    </p>
  </div>
@endsection
