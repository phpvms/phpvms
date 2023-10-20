<?php

namespace Tests;

use App\Models\Enums\UserState;
use App\Models\User;
use App\Notifications\Messages\AdminUserRegistered;
use App\Services\UserService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class RegistrationTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @throws \Exception
     *
     * @return void
     */
    public function testRegistration()
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
}
