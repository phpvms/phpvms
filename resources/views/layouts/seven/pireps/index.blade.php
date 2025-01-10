@extends('app')
@section('title', trans_choice('common.pirep', 2))

@section('content')
  <div class="row">
    <div class="col-md-12">
      <div class="float-end">
        <a class="btn btn-info pull-end"
           href="{{ route('frontend.pireps.create') }}">@lang('pireps.filenewpirep')</a>
      </div>
      <h2>{{ trans_choice('pireps.pilotreport', 2) }}</h2>
      @include('flash::message')
      @include('pireps.table')
    </div>
  </div>
  
@endsection

