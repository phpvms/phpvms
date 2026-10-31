@extends('auth.login_layout')
@section('title', __('Verify Your Email Address'))

@section('content')
    <x-airside::auth-card :title="__('Verify Your Email Address')">
        @if (session('resent'))
            <x-airside::alert kind="success">{{ __('A fresh verification link has been sent to your email address.') }}</x-airside::alert>
        @endif

        <p class="text-[13px]">
            {{ __('Before proceeding, please check your email for a verification link.') }}
            {{ __('If you did not receive the email') }},
        </p>

        <form method="POST" action="{{ route('verification.resend') }}">
            @csrf
            <x-ts-button submit block>{{ __('click here to request another') }}</x-ts-button>
        </form>
    </x-airside::auth-card>
@endsection
