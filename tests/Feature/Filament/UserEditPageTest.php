<?php

declare(strict_types=1);

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Airport;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

// notes is in User::$hidden, so it never reaches the form from the record's
// array form. A blank field on a page whose Save writes every key is how a
// pilot's file gets erased by someone who only came to change their rank.
it('keeps notes across a save that does not touch them', function (): void {
    $this->actingAs(createAdminUser());
    $airport = Airport::factory()->create();

    $user = User::factory()->create([
        'home_airport_id' => $airport->id,
        'pilot_id'        => 11,
        'notes'           => 'Grounded pending checkride.',
    ]);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->notes)->toBe('Grounded pending checkride.');
});

// A blank password box means "leave it alone", not "blank the password".
it('saves the identity fields from the page form and leaves a blank password unchanged', function (): void {
    $this->actingAs(createAdminUser());
    $user = User::factory()->create([
        'home_airport_id' => Airport::factory()->create()->id,
        'pilot_id'        => 11,
        'name'            => 'Old Name',
    ]);
    $hash = $user->password;

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm([
            'pilot_id' => 22,
            'name'     => 'New Name',
            'email'    => 'new.name@example.com',
            'password' => '',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh())
        ->pilot_id->toBe(22)
        ->name->toBe('New Name')
        ->email->toBe('new.name@example.com')
        ->password->toBe($hash);
});

it('hashes a password typed into the page form', function (): void {
    $this->actingAs(createAdminUser());
    $user = User::factory()->create(['home_airport_id' => Airport::factory()->create()->id]);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm(['password' => 'a-new-password'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('a-new-password', $user->refresh()->password))->toBeTrue();
});

it('soft-deletes the user from the save bar delete action', function (): void {
    $this->actingAs(createAdminUser());
    $user = User::factory()->create(['home_airport_id' => Airport::factory()->create()->id]);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->callAction(TestAction::make('delete')->schemaComponent(schema: 'saveBar'));

    expect($user->refresh()->trashed())->toBeTrue();
});
