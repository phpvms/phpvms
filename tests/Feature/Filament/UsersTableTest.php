<?php

declare(strict_types=1);

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Livewire\Livewire;

// ident is an accessor built from the airline code and pilot_id, not a column,
// so sorting on it has to go through pilot_id.
it('sorts the ident column by pilot_id', function (): void {
    $admin = createAdminUser(['pilot_id' => 50]);
    $this->actingAs($admin);

    $third = User::factory()->create(['pilot_id' => 30]);
    $first = User::factory()->create(['pilot_id' => 10]);
    $second = User::factory()->create(['pilot_id' => 20]);

    Livewire::test(ListUsers::class)
        ->sortTable('ident')
        ->assertCanSeeTableRecords([$first, $second, $third, $admin], inOrder: true)
        ->sortTable('ident', 'desc')
        ->assertCanSeeTableRecords([$admin, $third, $second, $first], inOrder: true);
});
