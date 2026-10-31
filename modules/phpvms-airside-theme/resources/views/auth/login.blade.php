@extends('auth.login_layout')
@section('title', __('common.login'))

@section('content')
    <x-airside::auth-card>
        <form method="post" action="{{ url('/login') }}" class="grid gap-4">
            @csrf

            <x-airside::field
                name="email"
                :label="__('common.email').' '.__('common.or').' '.__('common.pilot_id')"
                :value="old('email')"
                autocomplete="username"
                required
                autofocus
            />

            <x-airside::field
                name="password"
                type="password"
                :label="__('auth.password')"
                autocomplete="current-password"
                required
            />

            <x-ts-button submit block>{{ __('common.login') }}</x-ts-button>
        </form>

        @include('auth.oauth-connections', ['surface' => 'login'])

        <x-slot:footer>
            <a href="{{ url('/register') }}" wire:navigate>{{ __('auth.create_account') }}</a>
            <a href="{{ url('/password/reset') }}" wire:navigate>{{ __('auth.forgot_password') }}?</a>
        </x-slot:footer>
    </x-airside::auth-card>
@endsection
