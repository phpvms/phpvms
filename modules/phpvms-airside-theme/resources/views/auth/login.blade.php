@extends('auth.login_layout')
@section('title', __('common.login'))

@section('content')
    <x-vms::auth.card>
        <form method="post" action="{{ url('/login') }}" class="grid gap-4">
            @csrf

            <div class="grid gap-1">
                <x-ts-input :label="__('common.email').' '.__('common.or').' '.__('common.pilot_id')" name="email" id="email" type="text" :value="old('email')" invalidate autocomplete="username" required autofocus />

                @error('email')
                    <div role="alert"><x-ts-error property="email" /></div>
                @enderror
            </div>

            <div class="grid gap-1">
                <x-ts-password :label="__('auth.password')" name="password" id="password" invalidate autocomplete="current-password" required />

                @error('password')
                    <div role="alert"><x-ts-error property="password" /></div>
                @enderror
            </div>

            <x-ts-button submit block>{{ __('common.login') }}</x-ts-button>
        </form>

        @include('auth.oauth-connections', ['surface' => 'login'])

        <x-slot:footer>
            <a href="{{ url('/register') }}" wire:navigate>{{ __('auth.create_account') }}</a>
            <a href="{{ url('/password/reset') }}" wire:navigate>{{ __('auth.forgot_password') }}?</a>
        </x-slot:footer>
    </x-vms::auth.card>
@endsection
