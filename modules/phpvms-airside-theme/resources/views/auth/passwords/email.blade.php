@extends('auth.login_layout')
@section('title', __('auth.forgot_password'))

@section('content')
    <x-vms::auth.card :title="__('auth.forgot_password')">
        @if (session('status'))
            <x-ts-alert color="green" light shadowless role="status">{{ session('status') }}</x-ts-alert>
        @endif

        <form method="POST" action="{{ url('/password/email') }}" class="grid gap-4">
            @csrf

            <div class="grid gap-1">
                <x-ts-input :label="__('auth.email_address')" name="email" id="email" type="email" :value="old('email')" invalidate autocomplete="email" required autofocus />

                @error('email')
                    <div role="alert"><x-ts-error property="email" /></div>
                @enderror
            </div>

            <x-ts-button submit block>{{ __('auth.send_reset_link') }}</x-ts-button>
        </form>

        <x-slot:footer>
            <a href="{{ url('/login') }}" wire:navigate>{{ __('common.login') }}</a>
        </x-slot:footer>
    </x-vms::auth.card>
@endsection
