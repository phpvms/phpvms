@extends('app')
@section('title', __('home.welcome.title'))

@section('content')
  <div class="row">
    <div class="col-md-12">
      <h2>@lang('common.newestpilots')</h2>
      <div class="card border-0">
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5" style="margin: -.5rem; /* m-n2 */">
          @foreach ($users as $user)
            <div class="p-2">
              <div class="card bg-primary">
                <div class="text-center pt-4">
                  <h3 class="mb-4">
                    <a href="{{ route('frontend.profile.show', [$user->id]) }}"
                       class="text-white text-decoration-none fw-bold text-uppercase">{{ $user->name_private }}</a>
                  </h3>
                  <div class="photo-container">
                    @if ($user->avatar == null)
                      <img class="rounded-circle" src="{{ $user->gravatar(123) }}">
                    @else
                      <img src="{{ $user->avatar->url }}" style="width: 123px;">
                    @endif
                  </div>
                </div>
                <div class="card-body mx-auto">
                  <div class="text-center">
                    <h2 class="fw-light text-white mb-4">
                      @if (filled($user->home_airport))
                        {{ $user->home_airport->icao }}
                      @endif
                    </h2>
                  </div>
                  <a href="{{ route('frontend.profile.show', [$user->id]) }}"
                     class="btn btn-secondary btn-sm">@lang('common.profile')</a>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
@endsection
