@extends('app')
@section('title', __('pireps.file_flight_report'))

@section('content')
  <div class="row">
    <div class="col-md-12">
      <h2>@lang('pireps.new_flight_report')</h2>
      @include('flash::message')

      <form method="post" action="{{ route('frontend.pireps.store') }}">
        @csrf
        @include('pireps.fields')
      </form>
    </div>
  </div>
@endsection

@include('pireps.scripts')
