@inject('oauthConnectionService', 'App\Features\OAuth\Helpers\OAuthConnectionService')
@php
    $oauthConnections = $oauthConnectionService->enabledFor($surface);
    $intent = $surface === 'registration' ? 'register' : 'login';
@endphp

@if ($oauthConnections->isNotEmpty())
    <div class="flex items-center gap-3" aria-hidden="true">
        <hr class="flex-1" />
        <span class="text-xs">{{ __('auth.or') }}</span>
        <hr class="flex-1" />
    </div>

    <div class="grid gap-2">
        @foreach ($oauthConnections as $connection)
            @php
                $routeParameters = ['provider' => $connection->connection_id, 'intent' => $intent];
                $logoUrl = data_get($connection->configuration, 'logo_url');
                $buttonClass = data_get($connection->configuration, 'button_class');
                if ($surface === 'registration' && isset($invite)) {
                    $routeParameters['invite'] = $invite->id;
                    $routeParameters['token'] = $invite->token;
                }
            @endphp

            <a
                href="{{ route('oauth.redirect', $routeParameters) }}"
                @class([
                    'inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-[13px] font-medium no-underline',
                    $buttonClass => is_string($buttonClass) && $buttonClass !== '',
                ])
            >
                @if (is_string($logoUrl) && $logoUrl !== '')
                    <img src="{{ $logoUrl }}" alt="" aria-hidden="true" width="20" height="20" />
                @endif
                {{ __($surface === 'registration' ? 'auth.register_with' : 'auth.login_with', ['provider' => $connection->display_name]) }}
            </a>
        @endforeach
    </div>
@endif
