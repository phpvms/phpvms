@extends('app')
@section('title', __('pireps.fileflightreport'))

@section('content')
  <div class="row">
    <div class="col-md-12">
      <h2>@lang('pireps.newflightreport')</h2>
      @include('flash::message')

      <form method="post" action="{{ route('frontend.pireps.store') }}">
        @csrf
        @include('pireps.fields')
      </form>
    </div>
  </div>
@endsection

@include('pireps.scripts')
