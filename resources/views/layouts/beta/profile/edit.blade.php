@extends('app')
@section('title', __('profile.editprofile'))

@section('content')
  <div class="row">
    <div class="col-md-12">
      <h2 class="description">@lang('profile.edityourprofile')</h2>
      @include('flash::message')
      <form method="post" action="{{ route('frontend.profile.update', $user->id) }}" enctype="multipart/form-data">
        @csrf
        @method('PATCH')
        @include("profile.fields")
      </form>
    </div>
  </div>
@endsection

@section('scripts')
@include('scripts.airport_search')
@endsection
