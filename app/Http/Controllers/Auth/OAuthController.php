<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\Controller;
use App\Enums\UserState;
use App\Models\User;
use App\Models\UserOAuthToken;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    public function __construct(
        private readonly UserService $userSvc
    ) {}

    public function redirectToProvider(string $provider): RedirectResponse
    {
        if (!config('services.'.$provider.'.enabled', false)) {
            abort(404);
        }

        // Using a switch statement since we might need different scopes according to the provider
        switch ($provider) {
            case 'discord':
                if (!config('services.discord.enabled')) {
                    abort(404);
                }

                $requiredScopes = ['identify'];
                $envScopes = config('services.discord.scopes', []);
                $scopes = array_unique(array_merge($envScopes, $requiredScopes));

                return Socialite::driver('discord')->scopes($scopes)->redirect();
            case 'ivao':
                $scopes = config('services.ivao.scopes', []);

                return Socialite::driver('ivao')->scopes($scopes)->redirect();
            case 'vatsim':
                $requiredScopes = ['email'];
                $envScopes = config('services.vatsim.scopes', []);
                $scopes = array_unique(array_merge($envScopes, $requiredScopes));

                return Socialite::driver('vatsim')->scopes($scopes)->redirect();
            default:
                abort(404);
        }
    }

public function handleProviderCallback(string $provider, Request $request): View|RedirectResponse
    {
        $providerUser = null;

        if (!config('services.'.$provider.'.enabled', false)) {
            abort(404);
        }

        switch ($provider) {
            case 'discord':
                $providerUser = Socialite::driver('discord')->user();
                break;
            case 'ivao':
                $providerUser = Socialite::driver('ivao')->user();
                break;
            case 'vatsim':
                $providerUser = Socialite::driver('vatsim')->stateless()->user();
                break;
            default:
                abort(404);
        }

        if (!$providerUser) {
            flash()->error('Provider '.$provider.' not found');

            return redirect(url('/login'));
        }

        // If a user is logged in we want to link the account
        if (Auth::check()) {
            $user = Auth::user();

            $user->update([
                $provider.'_id' => $providerUser->getId(),
            ]);

            $tokens = UserOAuthToken::updateOrCreate([
                'user_id'  => $user->id,
                'provider' => $provider,
            ], [
                'token'             => $providerUser->token,
                'refresh_token'     => $providerUser->refreshToken,
                'last_refreshed_at' => now(),
            ]);

            if ($provider === 'discord') {
                $this->userSvc->retrieveDiscordPrivateChannelId($user);
            }

            flash()->success(ucfirst($provider).' account linked!');

            return redirect(route('frontend.profile.index'));
        }

        // 1. Standard phpVMS check: match by existing provider_id or email address
        $user = User::where($provider.'_id', $providerUser->getId())
            ->orWhere('email', $providerUser->getEmail())
            ->first();

        // 2. Custom VATSIM check: If no user found by email/provider_id, match by "VATSIM ID" custom profile field
        if (!$user && $provider === 'vatsim') {
            $matchedUserId = \Illuminate\Support\Facades\DB::table('user_field_values')
                ->join('user_fields', 'user_field_values.user_field_id', '=', 'user_fields.id')
                ->where('user_fields.name', 'VATSIM ID')
                ->where('user_field_values.value', $providerUser->getId())
                ->value('user_field_values.user_id');

            if ($matchedUserId) {
                $user = User::find($matchedUserId);
            }
        }

        if ($user) {
            // This links their vatsim_id column automatically for future logins
            $user->update([
                $provider.'_id' => $providerUser->getId(),
                'lastlogin_at'  => now(),
            ]);

            if (setting('general.record_user_ip', true)) {
                $user->update([
                    'last_ip' => $request->ip(),
                ]);
            }

            // We don't want to log in a non-active user
            if ($user->state !== UserState::ACTIVE && $user->state !== UserState::ON_LEAVE) {
                Log::info('Trying to login '.$user->ident.', state '.UserState::label($user->state));

                // Log them out
                Auth::logout();
                $request->session()->invalidate();

                // Redirect to one of the error pages
                if ($user->state === UserState::PENDING) {
                    return view('auth.pending');
                }

                if ($user->state === UserState::REJECTED) {
                    return view('auth.rejected');
                }

                if ($user->state === UserState::SUSPENDED) {
                    return view('auth.suspended');
                }
            }

            $tokens = UserOAuthToken::updateOrCreate([
                'user_id'  => $user->id,
                'provider' => $provider,
            ], [
                'token'             => $providerUser->token,
                'refresh_token'     => $providerUser->refreshToken,
                'last_refreshed_at' => now(),
            ]);

            Auth::login($user, true);

            if ($provider === 'discord') {
                $this->userSvc->retrieveDiscordPrivateChannelId($user);
            }

            return redirect(route('frontend.dashboard.index'));
        }

        flash()->error('No user linked to this account found. Please register first.');

        return redirect(url('/login'));
    }

    public function logoutProvider(string $provider): RedirectResponse
    {
        if (!config('services.'.$provider.'.enabled', false)) {
            abort(404);
        }

        $user = Auth::user();
        $otherProviders = UserOAuthToken::where('user_id', $user->id)->where('provider', '!=', $provider)->count();

        $user->update([
            $provider.'_id' => '',
        ]);

        if ($provider === 'discord' && $user->discord_private_channel_id) {
            $user->update([
                'discord_private_channel_id' => '',
            ]);
        }

        flash()->success(ucfirst($provider).' account unlinked!');

        return redirect()->route('frontend.profile.index');
    }
}
