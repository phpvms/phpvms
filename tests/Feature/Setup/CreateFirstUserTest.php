<?php

declare(strict_types=1);

use App\Features\Setup\Actions\CreateFirstUser as CreateFirstUserAction;
use App\Features\Setup\Support\UpgradeCompleted;
use App\Filament\Setup\CreateFirstUser;
use App\Http\Middleware\EnsureDatabaseCurrent;
use App\Models\Role;
use App\Models\User;
use App\Services\SettingService;
use App\Support\InstallMarker;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->dir = sys_get_temp_dir().'/phpvms-first-user-'.uniqid();
    mkdir($this->dir);
    $completed = new UpgradeCompleted($this->dir.'/completed', databaseSignal: false);
    $completed->write();

    app()->instance(UpgradeCompleted::class, $completed);
    DB::table('users')->delete();
    Filament::setCurrentPanel('admin');
});

afterEach(function (): void {
    array_map(unlink(...), glob($this->dir.'/*') ?: []);
    @rmdir($this->dir);
});

/** @return array<string, string> */
function firstUserForm(array $extra = []): array
{
    return array_merge([
        'name'                 => 'Ada Admin',
        'email'                => 'ada@example.com',
        'password'             => 'correct-horse-battery-9',
        'passwordConfirmation' => 'correct-horse-battery-9',
    ], $extra);
}

test('creates one verified super admin without an airline and sends them to login', function (): void {
    Livewire::test(CreateFirstUser::class)
        ->fillForm(firstUserForm())
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getPanel('admin')->getLoginUrl());

    $user = User::query()->sole();

    expect($user->email)->toBe('ada@example.com')
        ->and($user->hasRole(Role::superAdminName()))->toBeTrue()
        ->and(User::query()->whereKey($user->id)->value('airline_id'))->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->api_key)->not->toBeEmpty()
        ->and(Hash::check('correct-horse-battery-9', $user->password))->toBeTrue()
        ->and(setting('general.admin_email'))->toBe('ada@example.com')
        ->and(setting('general.setup_completed'))->toBeFalse()
        ->and(app(InstallMarker::class)->exists())->toBeTrue();

    $this->assertGuest();
});

test('the name is optional', function (): void {
    Livewire::test(CreateFirstUser::class)
        ->fillForm(firstUserForm(['name' => '']))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->count())->toBe(1);
});

test('creates nothing when the input is invalid', function (): void {
    Livewire::test(CreateFirstUser::class)
        ->fillForm(firstUserForm(['email' => 'not-an-email', 'passwordConfirmation' => 'different']))
        ->call('create')
        ->assertHasFormErrors(['email', 'password']);

    expect(User::query()->count())->toBe(0)
        ->and(app(InstallMarker::class)->exists())->toBeFalse();
});

test('a user created after the form opened wins: nothing is created and the visitor goes to login', function (): void {
    $component = Livewire::test(CreateFirstUser::class);

    $existing = User::factory()->create();

    $component->fillForm(firstUserForm())
        ->call('create')
        ->assertRedirect(Filament::getPanel('admin')->getLoginUrl());

    expect(User::query()->pluck('id')->all())->toBe([$existing->id])
        ->and(app(InstallMarker::class)->exists())->toBeFalse();
});

test('a trashed user counts too', function (): void {
    $component = Livewire::test(CreateFirstUser::class);

    User::factory()->create()->delete();

    $component->fillForm(firstUserForm())->call('create');

    expect(User::query()->count())->toBe(0)
        ->and(User::withTrashed()->count())->toBe(1);

    Livewire::test(CreateFirstUser::class)->assertStatus(403);
});

test('the page is closed before the install has finished', function (): void {
    unlink($this->dir.'/completed');

    Livewire::test(CreateFirstUser::class)->assertStatus(403);
});

test('a failed settings write rolls the user back and leaves setup marked complete', function (): void {
    $settings = Mockery::mock(SettingService::class);
    $settings->shouldReceive('storeMany')->andThrow(new RuntimeException('settings failed'));
    app()->instance(SettingService::class, $settings);

    expect(fn () => app(CreateFirstUserAction::class)->handle(['email' => 'ada@example.com', 'password' => 'correct-horse-battery-9']))
        ->toThrow(RuntimeException::class);

    expect(User::withTrashed()->count())->toBe(0)
        ->and(app(InstallMarker::class)->exists())->toBeFalse();
});

test('a replayed first-user snapshot is sent to the installer when the site is back to a fresh state', function (): void {
    $this->withMiddleware(EnsureDatabaseCurrent::class);

    $page = $this->get('/admin/first-user')->assertOk();
    preg_match('/wire:snapshot="([^"]+)"/', $page->getContent(), $match);
    $component = ['snapshot' => htmlspecialchars_decode($match[1], ENT_QUOTES), 'updates' => [], 'calls' => []];

    unlink($this->dir.'/completed');
    Livewire::flushState();

    $this->postJson(EndpointResolver::updatePath(), ['components' => [$component]], ['X-Livewire' => 'true'])
        ->assertRedirect('/admin/install');
});
