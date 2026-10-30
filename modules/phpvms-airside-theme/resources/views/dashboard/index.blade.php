@extends('app')
@inject('shell', 'App\Features\Shell\ShellData')

@section('title', __('common.dashboard'))

@section('content')
    @php($pilot = $shell->user())

    <x-airside::page name="dashboard" label="Dashboard">
        <x-airside::dashboard-pilot-header
            :pilot="$pilot"
            :dashboard="[
                'name' => $name,
                'flights' => $flights,
                'flightTimeMinutes' => $flightTimeMinutes,
                'transferTimeMinutes' => $transferTimeMinutes,
                'state' => $state,
                'onLeave' => $onLeave,
                'balance' => $balance,
                'currentAirport' => $currentAirport,
                'rank' => $rank,
                'pilotScore' => $pilotScore,
                'onTimePercentage' => $onTimePercentage,
                'averageLandingRate' => $averageLandingRate,
            ]"
        />

        <livewire:dashboard::dashboard-board :route="$route" />
    </x-airside::page>
@endsection
