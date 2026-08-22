<?php

declare(strict_types=1);

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Models\Airport;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = createAdminUser();
    $this->actingAs($this->admin);
    $this->airport = Airport::factory()->create();
});

// On the edit page pilot_id lives in the overview's settings drawer, not the
// page form, so these go through the drawer's Edit action.
it('saves an out-of-range pilot ID with the range enabled, without a validation error', function (): void {
    updateSetting('pilots.id_range_enabled', true);
    updateSetting('pilots.id_range_start', 100);
    updateSetting('pilots.id_range_end', 999);

    $user = User::factory()->create(['home_airport_id' => $this->airport->id, 'pilot_id' => 1000]);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->callAction('edit', ['pilot_id' => 5])
        ->assertHasNoActionErrors();

    expect($user->refresh()->pilot_id)->toBe(5);
});

it("allows reusing a soft-deleted user's pilot ID when id_reuse_deleted is on", function (): void {
    updateSetting('pilots.id_reuse_deleted', true);

    $trashed = User::factory()->create(['home_airport_id' => $this->airport->id, 'pilot_id' => 42]);
    $trashed->delete();

    $user = User::factory()->create(['home_airport_id' => $this->airport->id, 'pilot_id' => 1]);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->callAction('edit', ['pilot_id' => 42])
        ->assertHasNoActionErrors();

    expect($user->refresh()->pilot_id)->toBe(42);
});

it("blocks reusing a soft-deleted user's pilot ID when id_reuse_deleted is off", function (): void {
    updateSetting('pilots.id_reuse_deleted', false);

    $trashed = User::factory()->create(['home_airport_id' => $this->airport->id, 'pilot_id' => 42]);
    $trashed->delete();

    $user = User::factory()->create(['home_airport_id' => $this->airport->id, 'pilot_id' => 1]);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->callAction('edit', ['pilot_id' => 42])
        ->assertHasActionErrors(['pilot_id' => 'unique']);
});

// A blank password box means "leave it alone", not "blank the password".
it('saves the identity fields from the drawer and leaves a blank password unchanged', function (): void {
    $user = User::factory()->create([
        'home_airport_id' => $this->airport->id,
        'pilot_id'        => 7,
        'callsign'        => 'OLD',
    ]);

    $hash = $user->password;

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->callAction('edit', [
            'pilot_id' => 8,
            'callsign' => 'NEW',
            'name'     => 'Jane Doe',
            'email'    => 'jane.doe@example.com',
            'password' => '',
        ])
        ->assertHasNoActionErrors();

    expect($user->refresh())
        ->pilot_id->toBe(8)
        ->callsign->toBe('NEW')
        ->name->toBe('Jane Doe')
        ->email->toBe('jane.doe@example.com')
        ->password->toBe($hash);
});

it('hashes a password typed into the drawer', function (): void {
    $user = User::factory()->create(['home_airport_id' => $this->airport->id, 'pilot_id' => 9]);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->callAction('edit', ['password' => 'a-new-password'])
        ->assertHasNoActionErrors();

    expect(Hash::check('a-new-password', $user->refresh()->password))->toBeTrue();
});

it('returns no hint when the range is disabled', function (): void {
    updateSetting('pilots.id_range_enabled', false);

    expect(UserForm::pilotIdRangeHint(5))->toBeNull();
});

it('returns no hint when the value is within an enabled range', function (): void {
    updateSetting('pilots.id_range_enabled', true);
    updateSetting('pilots.id_range_start', 100);
    updateSetting('pilots.id_range_end', 999);

    expect(UserForm::pilotIdRangeHint(500))->toBeNull();
});

it('returns a warning hint when the value is outside an enabled range', function (): void {
    updateSetting('pilots.id_range_enabled', true);
    updateSetting('pilots.id_range_start', 100);
    updateSetting('pilots.id_range_end', 999);

    expect(UserForm::pilotIdRangeHint(5))
        ->toBe(__('filament.pilot_id_out_of_range_hint', ['start' => 100, 'end' => 999]));
});
