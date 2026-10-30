@extends('auth.login_layout')
@section('title', __('auth.registration_submitted'))

@section('content')
    <x-airside::auth-card :title="__('auth.registration_confirmation')">
        <p class="text-[13px] text-ink-dim">@lang('auth.confirmation_message')</p>
    </x-airside::auth-card>
@endsection
