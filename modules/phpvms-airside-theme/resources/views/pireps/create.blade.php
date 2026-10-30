@extends('app')
@section('title', __('pireps.file_flight_report'))

@section('content')
    <x-airside::page name="pirep-form" :label="__('pireps.new_flight_report')">
        <x-airside::page-header :headline="__('pireps.file_flight_report')" :title="__('pireps.new_flight_report')">
            <x-slot:links>
                <x-airside::back-link :href="route('frontend.pireps.index')">logbook</x-airside::back-link>
            </x-slot:links>
        </x-airside::page-header>

        <livewire:pireps::pirep-form :flight-id="request('flight_id')" :simbrief-id="$simbrief_id ?? null" />
    </x-airside::page>
@endsection
