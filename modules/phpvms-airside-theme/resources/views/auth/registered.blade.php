@extends('auth.login_layout')
@section('title', __('auth.registration_submitted'))

@section('content')
    <x-vms::auth.card :title="__('auth.registration_confirmation')">
        <p class="text-[13px]">@lang('auth.confirmation_message')</p>
    </x-vms::auth.card>
@endsection
