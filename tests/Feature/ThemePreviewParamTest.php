<?php

declare(strict_types=1);

use App\Http\Middleware\SetActiveTheme;
use App\Models\Permission;
use App\Models\User;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * `?preview_theme=` lets the admin's Theme page frame the site under a theme
 * before saving it. It must only work for an admin and only for a real theme.
 */
function previewRequest(?User $user, string $theme): Request
{
    $request = Request::create('/dashboard', 'GET', [SetActiveTheme::PREVIEW_PARAM => $theme]);
    $request->setUserResolver(fn (): ?User => $user);

    return $request;
}

function adminUser(): User
{
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'view:dashboard', 'guard_name' => 'web']);
    $user->givePermissionTo('view:dashboard');

    return $user->fresh();
}

beforeEach(function (): void {
    updateSetting('general.theme', 'seven');
});

it('previews an installed theme for a user with admin access', function (): void {
    new SetActiveTheme()->handle(previewRequest(adminUser(), 'airside'), fn (): Response => new Response());

    expect(Theme::get())->toBe('airside');
});

it('ignores the parameter for a user without admin access', function (): void {
    new SetActiveTheme()->handle(previewRequest(User::factory()->create(), 'airside'), fn (): Response => new Response());

    expect(Theme::get())->toBe('seven');
});

it('ignores the parameter for a guest', function (): void {
    new SetActiveTheme()->handle(previewRequest(null, 'airside'), fn (): Response => new Response());

    expect(Theme::get())->toBe('seven');
});

it('ignores a theme that is not installed', function (): void {
    new SetActiveTheme()->handle(previewRequest(adminUser(), 'does-not-exist'), fn (): Response => new Response());

    expect(Theme::get())->toBe('seven');
});
