@extends('app')
@section('title', __('pireps.file_flight_report'))

@section('content')
    <x-vms::page name="pirep-form" :label="__('pireps.new_flight_report')">
        <x-vms::page.header :headline="__('pireps.file_flight_report')" :title="__('pireps.new_flight_report')">
            <x-slot:links>
                <x-ts-link :href="route('frontend.pireps.index')" text="Back to logbook" icon="phosphor.arrow-left.light" sm navigate class="w-fit" />
            </x-slot:links>
        </x-vms::page.header>

        <livewire:pireps::pirep-form :flight-id="request('flight_id')" :simbrief-id="$simbrief_id ?? null" />
    </x-vms::page>
@endsection
