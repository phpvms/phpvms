<?php

use App\Enums\UserState;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Invite;
use App\Models\User;
use App\Notifications\Messages\AdminUserRegistered;
use App\Services\UserService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;

use function Pest\Laravel\seed;

beforeEach(function (): void {
    // Create a default user to prevent redirection to the installer
    User::factory()->create();
});

test('registration', function (): void {
    seed(RolesPermissionsSeeder::class);
    Notification::fake();

    $admin = createAdminUser(['name' => 'testRegistration Admin']);

    /** @var UserService $userSvc */
    $userSvc = app(UserService::class);

    updateSetting('pilots.auto_accept', true);

    $attrs = User::factory()->make()->makeVisible(['api_key', 'name', 'email'])->toArray();
    $attrs['password'] = Hash::make('secret');
    $attrs['flights'] = 0;
    $user = $userSvc->createUser($attrs);

    expect($user->state)->toEqual(UserState::ACTIVE);

    if ($user->markEmailAsVerified()) {
        event(new Verified($user));
    }

    Notification::assertSentTo([$admin], AdminUserRegistered::class);
    Notification::assertNotSentTo([$user], AdminUserRegistered::class);
});

function getUserData(): array
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

test('access to registration when registration enabled', function (): void {
    Notification::fake();

    updateSetting('general.disable_registrations', false);
    updateSetting('general.invite_only_registrations', false);

    $this->get('/register')
        ->assertOk();

    $this->post('/register', getUserData())
        ->assertRedirect('/dashboard');
});

test('mail transport failure does not fail registration and notifies admins', function (): void {
    seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser(['name' => 'Mail Failure Admin']);
    $mailer = Mockery::mock(Mailer::class);
    $mailer->shouldReceive('send')
        ->andThrow(new TransportException('Unable to connect to mailpit:1025'));
    $mailFactory = Mockery::mock(MailFactory::class);
    $mailFactory->shouldReceive('mailer')->andReturn($mailer);
    app()->instance(MailFactory::class, $mailFactory);

    updateSetting('general.disable_registrations', false);
    updateSetting('general.invite_only_registrations', false);

    $data = getUserData();

    $this->post('/register', $data)
        ->assertRedirect('/dashboard');

    $failureNotification = $admin->notifications()->get()->first(
        fn ($notification): bool => str_contains(
            $notification->data['body'] ?? '',
            'Unable to connect to mailpit:1025',
        ),
    );

    expect(User::where('email', $data['email'])->exists())->toBeTrue()
        ->and($failureNotification)->not->toBeNull();
});

// auto_accept_pireps is fillable for the admin pages, so the public
// registration form must not be able to smuggle the privilege flag in.
test('registration cannot mass-assign auto accept pireps', function (): void {
    Notification::fake();

    updateSetting('general.disable_registrations', false);
    updateSetting('general.invite_only_registrations', false);

    $data = getUserData();
    $data['auto_accept_pireps'] = 1;

    $this->post('/register', $data)
        ->assertRedirect('/dashboard');

    expect(User::where('email', $data['email'])->firstOrFail()->auto_accept_pireps)
        ->toBeFalse();
});

test('access to registration when registration disabled', function (): void {
    updateSetting('general.disable_registrations', true);

    $this->get('/register')
        ->assertForbidden();

    $this->post('/register', getUserData())
        ->assertForbidden();
});

test('access without invite', function (): void {
    updateSetting('general.disable_registrations', false);
    updateSetting('general.invite_only_registrations', true);

    $this->get('/register')
        ->assertForbidden();

    $this->post('/register', getUserData())
        ->assertForbidden();
});

test('access with valid invite', function (): void {
    Notification::fake();

    updateSetting('general.disable_registrations', false);
    updateSetting('general.invite_only_registrations', true);

    $invite = Invite::create([
        'token' => 'test',
    ]);

    $this->get($invite->link)
        ->assertOk();

    $userData = array_merge(getUserData(), [
        'invite'       => $invite->id,
        'invite_token' => base64_encode((string) $invite->token),
    ]);

    $this->post('/register', $userData)
        ->assertRedirect('/dashboard');
});

test('access with invalid invite', function (): void {
    updateSetting('general.disable_registrations', false);
    updateSetting('general.invite_only_registrations', true);

    // Expired invite
    $expiredInvite = Invite::create([
        'token'      => 'test',
        'expires_at' => now()->subDay(),
    ]);

    $expiredUserData = array_merge(getUserData(), [
        'invite'       => $expiredInvite->id,
        'invite_token' => base64_encode((string) $expiredInvite->token),
    ]);

    $this->get($expiredInvite->link)
        ->assertForbidden();

    $this->post('/register', $expiredUserData)
        ->assertForbidden();

    // Invalid token
    $invalidUserData = array_merge(getUserData(), [
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

    $tooUsedUserData = array_merge(getUserData(), [
        'invite'       => $tooUsedInvite->id,
        'invite_token' => base64_encode((string) $tooUsedInvite->token),
    ]);

    $this->get($tooUsedInvite->link)
        ->assertForbidden();

    $this->post('/register', $tooUsedUserData)
        ->assertForbidden();
});

test('with invalid email', function (): void {
    updateSetting('general.disable_registrations', false);
    updateSetting('general.invite_only_registrations', true);

    $invite = Invite::create([
        'email' => 'invited_email@phpvms.net',
        'token' => 'test',
    ]);

    $userData = array_merge(getUserData(), [
        'invite'       => $invite->id,
        'invite_token' => base64_encode((string) $invite->token),
    ]);

    $this->get($invite->link)
        ->assertOk();

    $this->post('/register', $userData)
        ->assertForbidden();
});
