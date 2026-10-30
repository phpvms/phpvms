@extends('app')

@section('title', __('errors.404.title'))

@section('content')
    <x-airside::error-panel code="404" :title="__('errors.404.title')" message="The page you requested does not exist.">
        <x-ts-button :href="route('frontend.home')" wire:navigate>Go home</x-ts-button>
        <x-ts-button :href="url()->previous()" outline>Go back</x-ts-button>
    </x-airside::error-panel>
@endsection
