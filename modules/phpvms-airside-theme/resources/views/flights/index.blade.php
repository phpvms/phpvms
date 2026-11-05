@extends('app')
@section('title', 'Flights')

@section('content')
    <livewire:flights::flight-manifest />
    <livewire:flights::flight-overview-drawer />
    <livewire:bids::assignment-drawer />
@endsection
