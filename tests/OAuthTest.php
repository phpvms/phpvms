<?php

namespace Tests;

use App\Models\Enums\UserState;
use App\Models\User;
use App\Models\UserOAuthToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class OAuthTest extends TestCase
{
    /** @var array|string[] The drivers we want to test */
    protected array $drivers = ['discord', 'ivao', 'vatsim'];

    protected function setUp(): void
    {
        parent::setUp();

        // Create a default user to prevent redirection to the installer
        User::factory()->create();

        foreach ($this->drivers as $driver) {
            Config::set('services.'.$driver.'.enabled', true);
        }
    }

    /**
     * Simulate what would be returned by the OAuth provider
     *
     * @return LegacyMockInterface|MockInterface
     */
    protected function getMockedProvider(): LegacyMockInterface|MockInterface
    {
        $abstractUser = \Mockery::mock('Laravel\Socialite\Two\User')
            ->allows([
                'getId'     => 123456789,
                'getName'   => 'OAuth user',
                'getEmail'  => 'oauth.user@phpvms.net',
                'getAvatar' => 'https://en.gravatar.com/userimage/12856995/aa6c0527a723abfd5fb9e246f0ff8af4.png',
            ]);

        $abstractUser->token = 'token';
        $abstractUser->refreshToken = 'refresh_token';

        return \Mockery::mock('Laravel\Socialite\Contracts\Provider')
            ->allows([
                'user' => $abstractUser,
            ]);
    }

    /**
     * Try to link a logged-in user to an OAuth account from profile
     *
     * @return void
     */
    public function testLinkAccountFromProfile(): void
    {
        $user = User::factory()->create([
            'name'  => 'OAuth user',
            'email' => 'oauth.user@phpvms.net',
        ]);
        Auth::login($user);

        foreach ($this->drivers as $driver) {
            Socialite::shouldReceive('driver')->with($driver)->andReturn($this->getMockedProvider());

            $this->get(route('oauth.callback', ['provider' => $driver]))
                ->assertRedirect(route('frontend.profile.index'));

            $user->refresh();
            $this->assertEquals(123456789, $user->{$driver.'_id'});

            $tokens = $user->oauth_tokens()->where('provider', $driver)->first();

            $this->assertNotNull($tokens);
            $this->assertEquals('token', $tokens->token);
            $this->assertEquals('refresh_token', $tokens->refresh_token);
            $this->assertTrue($tokens->last_refreshed_at->diffInSeconds(now()) <= 2);
        }
    }

    /**
     * Try to link a non-logged-in user from the login page using its email
     *
     * @return void
     */
    public function testLinkAccountFromLogin(): void
    {
        $user = User::factory()->create([
            'name'  => 'OAuth user',
            'email' => 'oauth.user@phpvms.net',
        ]);

        foreach ($this->drivers as $driver) {
            Socialite::shouldReceive('driver')->with($driver)->andReturn($this->getMockedProvider());

            $this->get(route('oauth.callback', ['provider' => $driver]))
                ->assertRedirect(route('frontend.dashboard.index'));

            $user->refresh();
            $this->assertEquals(123456789, $user->{$driver.'_id'});
            $this->assertTrue($user->lastlogin_at->diffInSeconds(now()) <= 2);

            $tokens = $user->oauth_tokens()->where('provider', $driver)->first();

            $this->assertNotNull($tokens);
            $this->assertEquals('token', $tokens->token);
            $this->assertEquals('refresh_token', $tokens->refresh_token);
            $this->assertTrue($tokens->last_refreshed_at->diffInSeconds(now()) <= 2);

            Auth::logout();
        }
    }

    /**
     * Try to log in an already linked user
     *
     * @return void
     */
    public function testLoginWithLinkedAccount(): void
    {
        $user = User::factory()->create([
            'name'  => 'OAuth user',
            'email' => 'oauth.user@phpvms.net',
        ]);

        foreach ($this->drivers as $driver) {
            $user->update([
                $driver.'_id' => 123456789,
            ]);

            UserOAuthToken::create([
                'user_id'           => $user->id,
                'provider'          => $driver,
                'token'             => 'token',
                'refresh_token'     => 'refresh_token',
                'last_refreshed_at' => now(),
            ]);

            Socialite::shouldReceive('driver')->with($driver)->andReturn($this->getMockedProvider());

            $this->get(route('oauth.callback', ['provider' => $driver]))
                ->assertRedirect(route('frontend.dashboard.index'));

            $user->refresh();
            $this->assertEquals(123456789, $user->{$driver.'_id'});
            $this->assertTrue($user->lastlogin_at->diffInSeconds(now()) <= 2);

            $tokens = $user->oauth_tokens()->where('provider', $driver)->first();

            $this->assertNotNull($tokens);
            $this->assertEquals('token', $tokens->token);
            $this->assertEquals('refresh_token', $tokens->refresh_token);
            $this->assertTrue($tokens->last_refreshed_at->diffInSeconds(now()) <= 2);

            Auth::logout();
        }
    }

    /**
     * Try to log in a user with a pending account
     *
     * @return void
     */
    public function testLoginWithPendingAccount(): void
    {
        $user = User::factory()->create([
            'name'  => 'OAuth user',
            'email' => 'oauth.user@phpvms.net',
            'state' => UserState::PENDING,
        ]);

        foreach ($this->drivers as $driver) {
            Socialite::shouldReceive('driver')->with($driver)->andReturn($this->getMockedProvider());

            $this->get(route('oauth.callback', ['provider' => $driver]))
                ->assertViewIs('auth.pending');
        }
    }

    /**
     * Try to log in someone not in DB
     *
     * @return void
     */
    public function testNoAccountFound()
    {
        foreach ($this->drivers as $driver) {
            Socialite::shouldReceive('driver')->with($driver)->andReturn($this->getMockedProvider());

            $this->get(route('oauth.callback', ['provider' => $driver]))
                ->assertRedirect(url('/login'));
        }
    }

    /**
     * Try to unlink an account from profile
     *
     * @return void
     */
    public function testUnlinkAccount(): void
    {
        $user = User::factory()->create([
            'name'  => 'OAuth user',
            'email' => 'oauth.user@phpvms.net',
        ]);

        foreach ($this->drivers as $driver) {
            $user->update([
                $driver.'_id' => 123456789,
            ]);

            Auth::login($user);

            $this->get(route('oauth.logout', ['provider' => $driver]))
                ->assertRedirect(route('frontend.profile.index'));

            $user->refresh();
            $this->assertEmpty($user->{$driver.'_id'});
        }
    }

    /**
     * Try to access a non-existing provider callback
     *
     * @return void
     */
    public function testNonExistingProvider(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->get(route('oauth.redirect', ['provider' => 'aze']))
            ->assertStatus(404);

        $this->get(route('oauth.callback', ['provider' => 'aze']))
            ->assertStatus(404);
    }

    /**
     * Try to access a disabled provider callback
     *
     * @return void
     */
    public function testDisabledProvider(): void
    {
        $originalConfigValue = config('services.discord.enabled');
        Config::set('services.discord.enabled', false);

        $this->expectException(NotFoundHttpException::class);

        $this->get(route('oauth.redirect', ['provider' => 'discord']))
            ->assertStatus(404);
        $this->get(route('oauth.callback', ['provider' => 'discord']))
            ->assertStatus(404);

        Config::set('services.discord.enabled', $originalConfigValue);
    }
}
