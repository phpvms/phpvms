@extends('auth.login_layout')
@section('title', __('auth.forgot_password'))

@section('content')
    <x-airside::auth-card :title="__('auth.forgot_password')">
        @if (session('status'))
            <x-airside::alert kind="success">{{ session('status') }}</x-airside::alert>
        @endif

        <form method="POST" action="{{ url('/password/email') }}" class="grid gap-4">
            @csrf

            <x-airside::field name="email" type="email" :label="__('auth.email_address')" :value="old('email')" autocomplete="email" required autofocus />

            <x-ts-button submit block>{{ __('auth.send_reset_link') }}</x-ts-button>
        </form>

        <x-slot:footer>
            <a href="{{ url('/login') }}" wire:navigate>{{ __('common.login') }}</a>
        </x-slot:footer>
    </x-airside::auth-card>
@endsection
