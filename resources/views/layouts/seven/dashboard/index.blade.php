@extends('app')
@section('title', __('common.dashboard'))

@section('content')
    <div class="row">
        <div class="col-md-8">
            @if (Auth::user()->state === \App\Models\Enums\UserState::ON_LEAVE)
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-warning" role="alert">
                            You are on leave! File a PIREP to set your status to active!
                        </div>
                    </div>
                </div>
            @endif

            {{-- TOP BAR WITH BOXES --}}
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white dashboard-box">
                        <div class="card-body text-center d-flex flex-center flex-column m-auto">
                            <h3 class="header">{{ $user->flights }}</h3>
                            <h5 class="description">{{ trans_choice('common.flight', $user->flights) }}</h5>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card bg-primary text-white dashboard-box">
                        <div class="card-body text-center">
                            <h3 class="header">@minutestotime($user->flight_time)</h3>
                            <h5 class="description">@lang('dashboard.totalhours')</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-primary text-white dashboard-box">
                        <div class="card-body text-center">
                            <h3 class="header">{{ optional($user->journal)->balance ?? 0 }}</h3>
                            <h5 class="description">@lang('dashboard.yourbalance')</h5>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card bg-primary text-white dashboard-box">
                        <div class="card-body text-center">
                            <h3 class="header">{{ $current_airport }}</h3>
                            <h5 class="description">@lang('airports.current')</h5>
                        </div>
                    </div>
                </div>

            </div>
            <div class="card mb-3">
                <div class="card-header bg-primary text-white" role="tablist">
                    @lang('dashboard.yourlastreport')
                </div>
                @if ($last_pirep === null)
                    <div class="card-body text-center">
                        @lang('dashboard.noreportsyet') <a href="{{ route('frontend.pireps.create') }}">@lang('dashboard.fileonenow')</a>
                    </div>
                @else
                    @include('dashboard.pirep_card', ['pirep' => $last_pirep])
                @endif
            </div>


            {{ Widget::latestNews(['count' => 5]) }}

        </div>

        {{-- Sidebar --}}
        <div class="col-sm-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    @lang('dashboard.weatherat', ['ICAO' => $current_airport])
                </div>
                <div class="card-body d-flex flex-column gap-4">
                    {{ Widget::Weather(['icao' => $current_airport]) }}
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header bg-primary text-white" role="tablist">
                    @lang('dashboard.recentreports')
                </div>
                <div class="card-body">
                    <!-- Tab panes -->
                    <div class="tab-content">
                        {{ Widget::latestPireps(['count' => 5]) }}
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header bg-primary text-white" role="tablist">
                    @lang('common.newestpilots')
                </div>
                <div class="card-body">
                    <!-- Tab panes -->
                    <div class="tab-content">
                        {{ Widget::latestPilots(['count' => 5]) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
