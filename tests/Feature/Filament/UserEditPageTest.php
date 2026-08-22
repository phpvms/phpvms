<?php

declare(strict_types=1);

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
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

// The drawer's preview strip is the only place an admin can see what the ident
// and the ATC callsign they are typing add up to. If it keeps showing the saved
// values it is worse than absent -- it reports the change did not take.
//
// Asserted against the modal's own markup, not the page's: a live update inside
// a mounted action skips the page render and answers with the `action-modals.N`
// partial alone, so the page HTML still carries the pre-edit ident and a plain
// assertSee would pass on that instead.
it('recomputes the drawer preview as the identity fields change', function (): void {
    $this->actingAs(createAdminUser());
    $airport = Airport::factory()->create();
    $airline = Airline::factory()->create(['icao' => 'XYZ']);
    $other = Airline::factory()->create(['icao' => 'QQQ']);

    $user = User::factory()->create([
        'home_airport_id' => $airport->id,
        'airline_id'      => $airline->id,
        'pilot_id'        => 11,
        'callsign'        => 'OLD',
        'name'            => 'Old Name',
    ]);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->mountAction('edit')
        ->assertMountedActionModalSee(['XYZ0011', 'XYZOLD', 'Old Name'])
        ->setActionData(['pilot_id' => 22, 'callsign' => 'NEW', 'name' => 'New Name'])
        ->assertMountedActionModalSee(['XYZ0022', 'XYZNEW', 'New Name'])
        ->assertMountedActionModalDontSee(['XYZ0011', 'XYZOLD'])
        // The airline drives the ident code, and the drawer can change it.
        ->setActionData(['airline_id' => $other->id])
        ->assertMountedActionModalSee(['QQQ0022', 'QQQNEW']);
});

// Heading, subheading and the overview cards all read the page's record. A
// drawer save that leaves them on the old values reads as a save that failed.
it('shows the saved identity in the heading and overview after the drawer saves', function (): void {
    $this->actingAs(createAdminUser());
    $airport = Airport::factory()->create();
    $airline = Airline::factory()->create(['icao' => 'AAA']);
    $other = Airline::factory()->create(['icao' => 'BBB', 'name' => 'Other Airways']);

    $user = User::factory()->create([
        'home_airport_id' => $airport->id,
        'airline_id'      => $airline->id,
        'pilot_id'        => 11,
        'name'            => 'Old Name',
    ]);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->callAction('edit', [
            'pilot_id'   => 22,
            'name'       => 'New Name',
            'airline_id' => $other->id,
        ])
        ->assertHasNoActionErrors()
        ->assertSee('BBB0022')
        ->assertSee('New Name')
        ->assertSee('Other Airways')
        ->assertDontSee('AAA0011')
        ->assertDontSee('Old Name');
});

// Delete moved from the page header into the drawer footer; it must still
// actually delete from there.
it('soft-deletes the user from the drawer footer delete action', function (): void {
    $this->actingAs(createAdminUser());
    $airport = Airport::factory()->create();

    $user = User::factory()->create(['home_airport_id' => $airport->id]);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->callAction([
            TestAction::make('edit'),
            TestAction::make('delete'),
        ]);

    expect($user->refresh()->trashed())->toBeTrue();
});
