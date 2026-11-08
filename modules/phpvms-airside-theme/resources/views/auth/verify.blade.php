@extends('auth.login_layout')
@section('title', __('Verify Your Email Address'))

@section('content')
    <x-vms::auth.card :title="__('Verify Your Email Address')">
        @if (session('resent'))
            <x-ts-alert color="green" light shadowless role="status">{{ __('A fresh verification link has been sent to your email address.') }}</x-ts-alert>
        @endif

        <p class="text-[13px]">
            {{ __('Before proceeding, please check your email for a verification link.') }}
            {{ __('If you did not receive the email') }},
        </p>

        <form method="POST" action="{{ route('verification.resend') }}">
            @csrf
            <x-ts-button submit block>{{ __('click here to request another') }}</x-ts-button>
        </form>
    </x-vms::auth.card>
@endsection
