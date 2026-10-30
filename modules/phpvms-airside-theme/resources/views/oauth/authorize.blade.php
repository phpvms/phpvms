@extends('auth.login_layout')
@section('title', __('oauth.authorized_apps'))
@section('card_width', 'max-w-[440px]')

@section('content')
    <x-airside::auth-card :title="$client->name">
        <p class="text-[13px] text-ink-dim">{{ __('oauth.authorize_request', ['client' => $client->name]) }}</p>

        @if (count($scopes) > 0)
            <div class="grid gap-2">
                <p class="micro">@lang('oauth.scopes')</p>
                <ul class="grid gap-1 text-[13px] text-ink">
                    @foreach ($scopes as $scope)
                        <li class="rounded-md border border-line bg-panel-inset px-3 py-1.5">{{ $scope->description }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-2 gap-2">
            <form method="post" action="{{ route('passport.authorizations.approve') }}" class="grid">
                @csrf
                <input type="hidden" name="state" value="{{ $request->state }}" />
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}" />
                <input type="hidden" name="auth_token" value="{{ $authToken }}" />
                <x-ts-button submit>{{ __('common.confirm') }}</x-ts-button>
            </form>

            <form method="post" action="{{ route('passport.authorizations.deny') }}" class="grid">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ $request->state }}" />
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}" />
                <input type="hidden" name="auth_token" value="{{ $authToken }}" />
                <x-ts-button submit color="secondary" outline>{{ __('common.cancel') }}</x-ts-button>
            </form>
        </div>
    </x-airside::auth-card>
@endsection
