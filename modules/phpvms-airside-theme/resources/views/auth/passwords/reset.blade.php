@extends('auth.login_layout')
@section('title', __('Reset Password'))

@section('content')
    <x-vms::auth.card :title="__('Reset Password')">
        <form method="post" action="{{ url('/password/reset') }}" class="grid gap-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}" />

            <div class="grid gap-1">
                <x-ts-input :label="__('Email Address')" name="email" id="email" type="email" :value="$email ?? old('email')" invalidate autocomplete="email" required autofocus />

                @error('email')
                    <div role="alert"><x-ts-error property="email" /></div>
                @enderror
            </div>
            <div class="grid gap-1">
                <x-ts-password :label="__('Password')" name="password" id="password" invalidate autocomplete="new-password" required />

                @error('password')
                    <div role="alert"><x-ts-error property="password" /></div>
                @enderror
            </div>
            <div class="grid gap-1">
                <x-ts-password :label="__('Confirm Password')" name="password_confirmation" id="password_confirmation" invalidate autocomplete="new-password" required />

                @error('password_confirmation')
                    <div role="alert"><x-ts-error property="password_confirmation" /></div>
                @enderror
            </div>

            <x-ts-button submit block>{{ __('Reset Password') }}</x-ts-button>
        </form>
    </x-vms::auth.card>
@endsection
