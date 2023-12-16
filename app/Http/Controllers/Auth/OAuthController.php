<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\Controller;
use GuzzleHttp\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    public function redirectToDiscordProvider(): RedirectResponse
    {
        return Socialite::driver('discord')->scopes(['identify'])->redirect();
    }

    public function handleDiscordProviderCallback(): RedirectResponse
    {
        $user = Socialite::driver('discord')->user();

        if ($user->getId()) {
            // Let's retrieve the private_channel_id
            if (!is_null(config('services.discord.token'))) {
                try {
                    $httpClient = new Client();
                    $response = $httpClient->request('POST', 'https://discord.com/api/users/@me/channels', [
                        'headers' => [
                            'Authorization' => 'Bot '.config('services.discord.token'),
                        ],
                        'json' => [
                            'recipient_id' => $user->getId(),
                        ],
                    ]);

                    $privateChannel = json_decode($response->getBody()->getContents(), true)['id'];
                } catch (\Exception $e) {
                    Log::error('Discord OAuth Error: '.$e->getMessage());
                    $privateChannel = null;
                }
            }

            Auth::user()?->update([
                'discord_id'                 => $user->getId(),
                'discord_private_channel_id' => $privateChannel ?? null,
            ]);

            flash()->success('Discord account linked!');
        } else {
            flash()->error('Unable to link Discord account!');
        }

        return redirect()->route('frontend.profile.index');
    }

    public function logoutDiscordProvider(): RedirectResponse
    {
        Auth::user()?->update([
            'discord_id'                 => null,
            'discord_private_channel_id' => null,
        ]);

        flash()->success('Discord account unlinked!');

        return redirect()->route('frontend.profile.index');
    }
}
