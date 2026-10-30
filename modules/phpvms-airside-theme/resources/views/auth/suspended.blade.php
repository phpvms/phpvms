@extends('auth.login_layout')
@section('title', __('auth.account_suspended'))

@section('content')
    <x-airside::auth-card :title="__('auth.account_suspended')">
        <p class="text-[13px] text-ink-dim">@lang('auth.suspended_message')</p>
    </x-airside::auth-card>
@endsection
