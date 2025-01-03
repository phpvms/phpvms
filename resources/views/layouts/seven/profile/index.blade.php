@extends('app')
@section('title', __('common.profile'))

@section('content')
    <div class="row">
        <div class="col-md-4 text-center">
            <div class="card">
                <div class="text-center pt-4">
                    @if ($user->avatar == null)
                        <img src="{{ $user->gravatar(512) }}" class="img-fluid card-img-top rounded-circle"
                            style="width: 123px;">
                    @else
                        <img src="{{ $user->avatar->url }}" class="img-fluid card-img-top rounded-circle"
                            style="width: 123px;">
                    @endif
                </div>
                <div class="card-body">
                    <h3 class="card-text">{{ $user->name_private }}</h3>
                    <p class="card-text">
                        {{ $user->ident }}
                        @if (filled($user->callsign))
                            {{ ' | ' . $user->callsign }}&nbsp;
                        @endif
                        <span class="fi fi-{{ $user->country }}"></span>
                    </p>
                    <p class="text-muted">
                        {{ $user->airline->name }}
                    </p>
                </div>
                <div>
                    @if (!empty($user->rank->image_url))
                        <img src="{{ $user->rank->image_url }}" class="img-fluid" style="width: 160px;">
                    @endif
                    <p>{{ $user->rank->name }} <br />
                        @if ($user->home_airport)
                            @lang('airports.home'): {{ $user->home_airport->icao }}
                        @endif
                    </p>
                </div>
                @if (Auth::check() && $user->id === Auth::user()->id)
                    <div class="card-footer d-grid gap-2">
                        @if (isset($acars) && $acars === true)
                            <a href="{{ route('frontend.profile.acars') }}" class="btn btn-info"
                                onclick="alert('Copy or Save to \'My Documents/vmsacars/profiles\'')">ACARS
                                Config</a>
                            &nbsp;
                        @endif
                        <a href="{{ route('frontend.profile.edit', [$user->id]) }}"
                            class="btn btn-primary">@lang('common.edit')</a>
                    </div>
                @endif
            </div>
        </div>
        <div class="col-md-8 mt-4 mt-sm-0">
            <div class="row mb-2 mb-sm-4">
                <div class="col-lg-6 mb-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h2 class="card-title">{{ $user->flights }}</h2>
                            <p class="card-text">{{ trans_choice('common.flight', $user->flights) }}</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card text-center">
                        <div class="card-body">
                            <div class="social-description">
                                <h2 class="card-title">@minutestotime($user->flight_time)</h2>
                                <p class="card-text">@lang('flights.flighthours')</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                @if ($user->current_airport)
                    <div class="col-lg-6 mb-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="social-description">
                                    <h2 class="card-title">{{ $user->current_airport->icao }}</h2>
                                    <p class="card-text">@lang('airports.current')</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif


                @if (setting('pilots.allow_transfer_hours') === true)
                    <div class="col-lg-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="social-description">
                                    <h2 class="card-title">@minutestohours($user->transfer_time)h</h2>
                                    <p class="card-text">@lang('profile.transferhours')</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Show the user's award if they have any --}}
    @if ($user->awards)
        <div class="row mt-5">
            <div class="col-sm-12">
                <h3>@lang('profile.your-awards')</h3>
                @foreach ($user->awards->chunk(3) as $awards)
                    <div class="row">
                        @foreach ($awards as $award)
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header bg-primary text-white bg-primary text-center">
                                        <h4 class="text-white">{{ $award->name }}</h4>
                                        @if ($award->image_url)
                                            <img src="{{ $award->image_url }}" alt="{{ $award->description }}"
                                                class="img-fluid card-img-top" style="width: 123px;">
                                        @endif
                                    </div>
                                    <div class="card-body text-center">
                                        {{ $award->description }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

    @endif
@endsection
