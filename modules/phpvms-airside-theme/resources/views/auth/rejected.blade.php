@extends('auth.login_layout')
@section('title', __('auth.registration_denied'))

@section('content')
    <x-vms::auth.card :title="__('auth.registration_denied')">
        <p class="text-[13px]">@lang('auth.denied_message')</p>
    </x-vms::auth.card>
@endsection
