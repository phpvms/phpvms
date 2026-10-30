@extends('app')
@section('title', __('pireps.edit_flight_report'))

@section('content')
    <x-airside::page name="pirep-form" :label="__('pireps.edit_flight_report')">
        <x-airside::page-header :headline="$pirep->ident" :title="__('pireps.edit_flight_report')">
            <x-slot:links>
                <x-airside::back-link :href="route('frontend.pireps.show', [$pirep->id])">{{ $pirep->ident }}</x-airside::back-link>
            </x-slot:links>
        </x-airside::page-header>

        <livewire:pireps::pirep-form :pirep-id="$pirep->id" />
    </x-airside::page>
@endsection
