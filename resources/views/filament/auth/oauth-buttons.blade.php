{{--
  Social (SSO) sign-in buttons for the Filament panel login, rendered below the
  credentials form via the panels::auth.login.form.after render hook in
  BasePanelProvider.

  Same source of truth as the pilot-facing login partial
  (layouts/seven/auth/oauth-connections.blade.php): OAuthConnectionService
  filters to connections that are enabled, flagged for the "login" surface, and
  whose Socialite provider package is actually installed. Only the markup
  differs -- that partial is Bootstrap, this one is Filament/Tailwind.

  Registration and account-linking are deliberately not offered here: the admin
  panel is a sign-in surface only.
--}}
@php
    $oauthConnections = app(\App\Features\OAuth\Helpers\OAuthConnectionService::class)->enabledFor('login');
@endphp

@if ($oauthConnections->isNotEmpty())
    <div class="fi-sso mt-6 flex flex-col gap-3">
        <div class="flex items-center gap-3" aria-hidden="true">
            <hr class="fi-sso-rule grow border-gray-200 dark:border-white/10" />
            <span class="fi-sso-divider text-sm text-gray-500 dark:text-gray-400">
                {{ __('auth.or') }}
            </span>
            <hr class="fi-sso-rule grow border-gray-200 dark:border-white/10" />
        </div>

        @foreach ($oauthConnections as $connection)
            @php
                $logoUrl = data_get($connection->configuration, 'logo_url');
                $buttonClass = data_get($connection->configuration, 'button_class');
            @endphp

            {{--
              spa-mode must be false. The panel runs with spa(), and
              generate_href_html() adds wire:navigate to any same-origin href --
              which /oauth/{provider}/redirect is, being a local route. Livewire
              would then fetch() it, follow its 302 out to the identity provider
              cross-origin, and die on CORS. Prefetching makes it worse: hovering
              would burn an OAuth state/nonce before the click.
            --}}
            <x-filament::button
                tag="a"
                color="gray"
                outlined
                :spa-mode="false"
                @class(['fi-sso-btn w-full justify-center', $buttonClass => is_string($buttonClass) && $buttonClass !== ''])
                :href="route('oauth.redirect', ['provider' => $connection->connection_id, 'intent' => 'login'])"
            >
                @if (is_string($logoUrl) && $logoUrl !== '')
                    <img src="{{ $logoUrl }}" alt="" aria-hidden="true" width="20" height="20" class="me-2 h-5 w-5" />
                @endif

                {{ __('auth.login_with', ['provider' => $connection->display_name]) }}
            </x-filament::button>
        @endforeach
    </div>
@endif
