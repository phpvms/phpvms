@extends('auth.login_layout')
@section('title', __('Reset Password'))

@section('content')
    <x-airside::auth-card :title="__('Reset Password')">
        <form method="post" action="{{ url('/password/reset') }}" class="grid gap-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}" />

            <x-airside::field name="email" type="email" :label="__('Email Address')" :value="$email ?? old('email')" autocomplete="email" required autofocus />
            <x-airside::field name="password" type="password" :label="__('Password')" autocomplete="new-password" required />
            <x-airside::field name="password_confirmation" type="password" :label="__('Confirm Password')" autocomplete="new-password" required />

            <x-ts-button submit block>{{ __('Reset Password') }}</x-ts-button>
        </form>
    </x-airside::auth-card>
@endsection
