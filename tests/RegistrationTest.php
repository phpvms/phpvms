<?php

namespace Tests;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Enums\UserState;
use App\Models\Invite;
use App\Models\User;
use App\Notifications\Messages\AdminUserRegistered;
use App\Services\UserService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class RegistrationTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Create a default user to prevent redirection to the installer
        User::factory()->create();

    }


    /**
     * A basic test example.
     *
     * @throws \Exception
     *
     * @return void
     */
    public function testRegistration(): void
    {
        $admin = $this->createAdminUser(['name' => 'testRegistration Admin']);

        /** @var UserService $userSvc */
        $userSvc = app(UserService::class);

        $this->updateSetting('pilots.auto_accept', true);

        $attrs = User::factory()->make()->makeVisible(['api_key', 'name', 'email'])->toArray();
        $attrs['password'] = Hash::make('secret');
        $attrs['flights'] = 0;
        $user = $userSvc->createUser($attrs);

        $this->assertEquals(UserState::ACTIVE, $user->state);

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        Notification::assertSentTo([$admin], AdminUserRegistered::class);
        Notification::assertNotSentTo([$user], AdminUserRegistered::class);
    }

    protected function getUserData(): array
    {
        $airline = Airline::factory()->create();
        $home = Airport::factory()->create(['hub' => true]);

        return [
            'name'                  => 'Test User',
            'email'                 => 'test@phpvms.net',
            'airline_id'            => $airline->id,
            'home_airport_id'       => $home->id,
            'password'              => 'secret',
            'password_confirmation' => 'secret',
            'toc_accepted'          => true,
        ];
    }

    public function testAccessToRegistrationWhenRegistrationEnabled(): void
    {
        $this->updateSetting('general.disable_registrations', false);
        $this->updateSetting('general.invite_only_registrations', false);

        $this->get('/register')
            ->assertOk();

        $this->post('/register', $this->getUserData())
            ->assertRedirect('/dashboard');
    }

    public function testAccessToRegistrationWhenRegistrationDisabled(): void
    {
        $this->updateSetting('general.disable_registrations', true);

        $this->expectException(HttpException::class);

        $this->get('/register')
            ->assertForbidden();

        $this->post('/register', $this->getUserData())
            ->assertForbidden();
    }

    public function testAccessWithoutInvite(): void
    {
        $this->updateSetting('general.disable_registrations', false);
        $this->updateSetting('general.invite_only_registrations', true);

        $this->expectException(HttpException::class);

        $this->get('/register')
            ->assertForbidden();

        $this->post('/register', $this->getUserData())
            ->assertForbidden();
    }

    public function testAccessWithValidInvite(): void
    {
        $this->updateSetting('general.disable_registrations', false);
        $this->updateSetting('general.invite_only_registrations', true);

        $invite = Invite::create([
            'token' => 'test',
        ]);

        $this->get($invite->link)
            ->assertOk();

        $userData = array_merge($this->getUserData(), [
            'invite'       => $invite->id,
            'invite_token' => base64_encode($invite->token),
        ]);

        $this->post('/register', $userData)
            ->assertRedirect('/dashboard');
    }

    public function testAccessWithInvalidInvite(): void
    {
        $this->updateSetting('general.disable_registrations', false);
        $this->updateSetting('general.invite_only_registrations', true);

        $this->expectException(HttpException::class);

        // Expired invite
        $expiredInvite = Invite::create([
            'token'      => 'test',
            'expires_at' => now()->subDay(),
        ]);

        $expiredUserData = array_merge($this->getUserData(), [
            'invite'       => $expiredInvite->id,
            'invite_token' => base64_encode($expiredInvite->token),
        ]);

        $this->get($expiredInvite->link)
            ->assertForbidden();

        $this->post('/register', $expiredUserData)
            ->assertForbidden();

        // Invalid token
        $invalidUserData = array_merge($this->getUserData(), [
            'invite'       => 1,
            'invite_token' => 'invalid',
        ]);

        $this->get('/register?invite=1&invite_token=invalid')
            ->assertForbidden();

        $this->post('/register', $invalidUserData)
            ->assertForbidden();

        // Invite used too many times
        $tooUsedInvite = Invite::create([
            'token'       => 'test',
            'usage_count' => 1,
            'usage_limit' => 1,
        ]);

        $tooUsedUserData = array_merge($this->getUserData(), [
            'invite'       => $tooUsedInvite->id,
            'invite_token' => base64_encode($tooUsedInvite->token),
        ]);

        $this->get($tooUsedInvite->link)
            ->assertForbidden();

        $this->post('/register', $tooUsedUserData)
            ->assertForbidden();
    }

    public function testWithInvalidEmail(): void
    {
        $this->updateSetting('general.disable_registrations', false);
        $this->updateSetting('general.invite_only_registrations', true);

        $this->expectException(HttpException::class);
        $invite = Invite::create([
            'email' => 'invited_email@phpvms.net',
            'token' => 'test',
        ]);

        $userData = array_merge($this->getUserData(), [
            'invite'       => $invite->id,
            'invite_token' => base64_encode($invite->token),
        ]);

        $this->get($invite->link)
            ->assertOk();

        $this->post('/register', $userData)
            ->assertForbidden();
    }
}
