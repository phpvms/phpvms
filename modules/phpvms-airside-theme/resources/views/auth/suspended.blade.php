@extends('auth.login_layout')
@section('title', __('auth.account_suspended'))

@section('content')
    <x-vms::auth.card :title="__('auth.account_suspended')">
        <p class="text-[13px]">@lang('auth.suspended_message')</p>
    </x-vms::auth.card>
@endsection
