@extends('app')
@section('title', __('pireps.edit_flight_report'))
@section('content')
  <div class="row">
    <div class="col-md-12">
      <h2>@lang('pireps.edit_flight_report')</h2>
      @include('flash::message')
      <form method="post" action="{{ route('frontend.pireps.update', $pirep->id) }}" class="form-group">
        @method('PATCH')
        @csrf

        @include('pireps.fields')
      </form>
    </div>
  </div>
@endsection
@include('pireps.scripts')
