<?php

declare(strict_types=1);

use App\Filament\System\Installer;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

it('allows access to the installer before any user exists', function (): void {
    expect(Schema::hasTable('users'))->toBeTrue()
        ->and(User::query()->withoutGlobalScopes()->exists())->toBeFalse()
        ->and(Installer::canAccess())->toBeTrue();
});

it('disables the installer once an admin user exists', function (): void {
    User::factory()->create();

    expect(Installer::canAccess())->toBeFalse();
});
