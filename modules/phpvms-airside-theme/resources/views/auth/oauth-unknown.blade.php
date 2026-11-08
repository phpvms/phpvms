@extends('auth.login_layout')
@section('title', __('auth.oauth_not_linked_title'))
@section('card_width', 'max-w-[440px]')

@section('content')
    <x-vms::auth.card :title="__('auth.oauth_not_linked_title')" :description="__('auth.oauth_not_linked_message', ['provider' => $connection->display_name])">
        @if ($verifiedEmailMatch)
            <x-ts-alert color="primary" light shadowless role="status">{{ __('auth.oauth_verified_email_match', ['email' => $pending['email']]) }}</x-ts-alert>
        @endif

        <div class="grid gap-2">
            @if ($registrationAvailable)
                <form method="post" action="{{ route('oauth.register', ['provider' => $connection->connection_id]) }}" class="grid">
                    @csrf
                    <x-ts-button submit block>{{ __('auth.oauth_create_pilot') }}</x-ts-button>
                </form>
            @endif

            @if ($connection->linking_enabled)
                <form method="post" action="{{ route('oauth.link', ['provider' => $connection->connection_id]) }}" class="grid">
                    @csrf
                    <x-ts-button submit block color="secondary" outline>{{ __('auth.oauth_link_existing') }}</x-ts-button>
                </form>
            @endif
        </div>

        <x-slot:footer>
            <form method="post" action="{{ route('oauth.cancel') }}">
                @csrf
                <button type="submit">{{ __('auth.oauth_cancel') }}</button>
            </form>
        </x-slot:footer>
    </x-vms::auth.card>
@endsection
