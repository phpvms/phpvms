<?php

declare(strict_types=1);

use App\Features\Shell\ShellData;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\NavigationItem;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Http\Request;

/**
 * theme-render-contract: "Shell data is the same for every theme kind".
 */
function shellRequest(?User $user, array $session = []): Request
{
    $request = Request::create('/dashboard');
    $store = app('session')->driver();
    foreach ($session as $key => $value) {
        $store->put($key, $value);
    }

    $request->setLaravelSession($store);
    $request->setUserResolver(fn (): ?User => $user);

    return $request;
}

it('gives a Blade theme the same navigation, user and flash as the Inertia shared props', function (): void {
    $user = User::factory()->create();
    NavigationItem::factory()->count(2)->create();
    flash()->success('Profile updated successfully!');
    $request = shellRequest($user, [
        'flash_notification' => session('flash_notification'),
        'plain_text_token'   => 'tok-123',
    ]);

    $shared = app(HandleInertiaRequests::class)->share($request);
    $shell = app(ShellData::class, ['request' => $request]);

    expect($shell->navigation())->not->toBeEmpty()
        ->and($shell->navigation())->toBe(($shared['navigation'])())
        ->and($shell->user())->toBe($shared['auth']['user'])
        ->and($shell->flash())->toBe([
            'success'        => ($shared['flash']['success'])(),
            'error'          => ($shared['flash']['error'])(),
            'plainTextToken' => ($shared['flash']['plainTextToken'])(),
        ])
        ->and($shell->flash()['success'])->toBe('Profile updated successfully!')
        ->and($shell->flash()['plainTextToken'])->toBe('tok-123');
});

it('knows whether the viewer may open the admin panel', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $pilot = app(ShellData::class, ['request' => shellRequest(User::factory()->create())]);
    $admin = app(ShellData::class, ['request' => shellRequest(createAdminUser())]);
    $guest = app(ShellData::class, ['request' => shellRequest(null)]);

    expect($pilot->canAccessAdmin())->toBeFalse()
        ->and($admin->canAccessAdmin())->toBeTrue()
        ->and($guest->canAccessAdmin())->toBeFalse();
});

it('has no user or pilot chrome for a guest', function (): void {
    $shell = app(ShellData::class, ['request' => shellRequest(null)]);

    expect($shell->user())->toBeNull()
        ->and($shell->pilotChrome())->toBeNull();
});
