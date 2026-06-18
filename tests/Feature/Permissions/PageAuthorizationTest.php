<?php

declare(strict_types=1);

use App\Filament\Pages\Settings;
use App\Filament\Widgets\LatestPirepsChart;
use App\Models\Permission;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

function userWithPermissions(string ...$permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        $user->givePermissionTo($name);
    }

    return $user->fresh();
}

it('denies page access without the view permission', function (): void {
    $this->actingAs(userWithPermissions());

    expect(Settings::canAccess())->toBeFalse();
});

it('allows page access with the view permission but blocks edit', function (): void {
    $this->actingAs(userWithPermissions('view:settings'));

    expect(Settings::canAccess())->toBeTrue();
    expect(Settings::canEdit())->toBeFalse();
});

it('allows the save action only with the edit permission', function (): void {
    $this->actingAs(userWithPermissions('view:settings', 'edit:settings'));

    expect(Settings::canAccess())->toBeTrue();
    expect(Settings::canEdit())->toBeTrue();
});

it('shows a widget regardless of permissions', function (): void {
    // Widgets are not authorized individually; access to the page/resource that
    // renders them is the gate.
    $this->actingAs(userWithPermissions());

    expect(LatestPirepsChart::canView())->toBeTrue();
});
