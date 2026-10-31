@extends('auth.login_layout')
@section('title', __('auth.registration_pending'))

@section('content')
    <x-vms::auth.card :title="__('auth.registration_pending')">
        <p class="text-[13px]">@lang('auth.pending_message')</p>
    </x-vms::auth.card>
@endsection
