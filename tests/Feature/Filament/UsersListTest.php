<?php

declare(strict_types=1);

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Livewire\Livewire;

// The ident column reads $user->airline per row; with lazy loading prevented
// outside production, an un-eager-loaded list throws instead of silently N+1ing.
it('lists users with their airline-derived ident without lazy loading', function (): void {
    $this->actingAs(createAdminUser());
    $users = User::factory()->count(3)->create();

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords($users);
});
