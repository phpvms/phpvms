@inject('oauthConnectionService', 'App\Features\OAuth\Helpers\OAuthConnectionService')
@php
  $oauthConnections = $oauthConnectionService->enabledFor($surface);
  $intent = $surface === 'registration' ? 'register' : 'login';
@endphp

@if($oauthConnections->isNotEmpty())
  <div class="d-flex align-items-center gap-3 my-3" aria-hidden="true">
    <hr class="flex-grow-1 my-0">
    <span class="text-muted small">@lang('auth.or')</span>
    <hr class="flex-grow-1 my-0">
  </div>

  <div class="d-grid gap-2">
    @foreach($oauthConnections as $connection)
      @php
        $routeParameters = ['provider' => $connection->connection_id, 'intent' => $intent];
        $logoUrl = data_get($connection->configuration, 'logo_url');
        if ($surface === 'registration' && isset($invite)) {
          $routeParameters['invite'] = $invite->id;
          $routeParameters['token'] = $invite->token;
        }
      @endphp
      <a href="{{ route('oauth.redirect', $routeParameters) }}"
        class="btn btn-outline-secondary d-flex align-items-center justify-content-center gap-2">
        @if(is_string($logoUrl) && $logoUrl !== '')
          <img src="{{ $logoUrl }}" alt="" aria-hidden="true" width="20" height="20">
        @endif
        @lang($surface === 'registration' ? 'auth.register_with' : 'auth.login_with', [
          'provider' => $connection->display_name,
        ])
      </a>
    @endforeach
  </div>
@endif
