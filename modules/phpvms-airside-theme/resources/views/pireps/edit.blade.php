@extends('app')
@section('title', __('pireps.edit_flight_report'))

@section('content')
    <x-vms::page name="pirep-form" :label="__('pireps.edit_flight_report')">
        <x-vms::page.header :title="__('pireps.edit_flight_report')">
            <x-slot:links>
                <x-ts-link :href="route('frontend.pireps.show', [$pirep->id])" text="Back to {{ $pirep->ident }}" icon="phosphor.arrow-left.light" sm navigate class="w-fit" />
            </x-slot:links>
        </x-vms::page.header>

        <livewire:pireps::pirep-form :pirep-id="$pirep->id" />
    </x-vms::page>
@endsection
