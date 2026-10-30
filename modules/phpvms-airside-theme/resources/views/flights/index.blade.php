@extends('app')
@section('title', 'Flights')

@section('content')
    <livewire:flights::flight-manifest />
    <livewire:bids::assignment-drawer />
@endsection
