@extends('auth.login_layout')
@section('title', __('auth.registration_denied'))

@section('content')
    <x-airside::auth-card :title="__('auth.registration_denied')">
        <p class="text-[13px] text-ink-dim">@lang('auth.denied_message')</p>
    </x-airside::auth-card>
@endsection
