@extends('app')

@section('title', __('errors.401.title'))

@section('content')
    <x-vms::errors.panel code="401" :title="__('errors.401.title')" message="You are not authorized to access or perform this function.">
        <x-ts-button :href="route('frontend.home')" wire:navigate>Go home</x-ts-button>
        <x-ts-button :href="url()->previous()" outline>Go back</x-ts-button>
    </x-vms::errors.panel>
@endsection
