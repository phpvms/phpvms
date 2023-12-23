<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\Controller;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\User;
use App\Models\UserOAuthToken;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    public function __construct(
        private readonly UserService $userSvc
    ) {
    }

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
                return Socialite::driver('discord')->scopes(['identify'])->redirect();
            default:
                abort(404);
        }
    }

    public function handleProviderCallback(string $provider): RedirectResponse
    {
        $providerUser = null;

        if (!config('services.'.$provider.'.enabled', false)) {
            abort(404);
        }

        switch ($provider) {
            case 'discord':
                $providerUser = Socialite::driver('discord')->user();
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

            flash()->success(ucfirst($provider).' account linked!');

            return redirect(route('frontend.profile.index'));
        }

        $user = User::where($provider.'_id', $providerUser->getId())->first();

        if ($user) {
            $tokens = UserOAuthToken::updateOrCreate([
                'user_id'  => $user->id,
                'provider' => $provider,
            ], [
                'token'             => $providerUser->token,
                'refresh_token'     => $providerUser->refreshToken,
                'last_refreshed_at' => now(),
            ]);

            Auth::login($user);

            return redirect(route('frontend.dashboard.index'));
        }

        $attrs = [
            'name'            => $providerUser->getName(),
            'email'           => $providerUser->getEmail(),
            'avatar'          => $providerUser->getAvatar(),
            'airline_id'      => Airline::select('id')->first()->id,
            'home_airport_id' => Airport::select('id')->where('hub', true)->first()->id,
            $provider.'_id'   => $providerUser->getId(),
        ];

        $user = $this->userSvc->createUser($attrs);

        UserOAuthToken::create([
            'user_id'           => $user->id,
            'provider'          => $provider,
            'token'             => $providerUser->token,
            'refresh_token'     => $providerUser->refreshToken,
            'last_refreshed_at' => now(),
        ]);

        Auth::login($user);

        return redirect(route('frontend.profile.edit', ['profile' => $user->id]));
    }

    public function logoutProvider(string $provider): RedirectResponse
    {
        if (!config('services.'.$provider.'.enabled', false)) {
            abort(404);
        }

        $user = Auth::user();
        $otherProviders = UserOAuthToken::where('user_id', $user->id)->where('provider', '!=', $provider)->count();

        if (empty($user->password) && $otherProviders === 0) {
            flash()->error('You cannot unlink your only login method!');
            return redirect()->route('frontend.profile.index');
        }

        $user->update([
            $provider.'_id' => null,
        ]);

        flash()->success(ucfirst($provider).' account unlinked!');

        return redirect()->route('frontend.profile.index');
    }
}
