<?php

declare(strict_types=1);

require_once __DIR__.'/../Support.php';

use App\Features\Profile\Livewire\ProfileEdit;
use App\Models\Airport;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/**
 * 7.10: the ProfileEdit form object and the home airport search.
 */
beforeEach(function (): void {
    $this->pilot = useAirside();
    $this->withoutVite();
    $this->actingAs($this->pilot);
});

it('starts from the saved profile', function (): void {
    $this->pilot->update(['name' => 'Ada Lovelace', 'simbrief_username' => 'ada']);

    Livewire::test(ProfileEdit::class)
        ->assertSet('form.name', 'Ada Lovelace')
        ->assertSet('form.email', $this->pilot->email)
        ->assertSet('form.simbrief_username', 'ada');
});

it('saves the profile with a lower-cased email and the opt-in coerced', function (): void {
    $field = UserField::query()->create(['name' => 'Favourite Type', 'internal' => false, 'active' => true]);

    Livewire::test(ProfileEdit::class)
        ->set('form.name', 'New Name')
        ->set('form.email', '  New.Address@Example.COM ')
        ->set('form.opt_in', true)
        ->set('form.simbrief_username', 'newname')
        ->set('form.fields.field_'.$field->slug, 'B738')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('frontend.profile.index'));

    $this->pilot->refresh();

    expect($this->pilot->name)->toBe('New Name')
        ->and($this->pilot->email)->toBe('new.address@example.com')
        ->and((bool) $this->pilot->opt_in)->toBeTrue()
        ->and($this->pilot->simbrief_username)->toBe('newname')
        ->and(UserFieldValue::query()->where(['user_id' => $this->pilot->id, 'user_field_id' => $field->id])->value('value'))->toBe('B738');
});

it('unchecking opt-in turns it off', function (): void {
    $this->pilot->update(['opt_in' => true]);

    Livewire::test(ProfileEdit::class)
        ->set('form.opt_in', false)
        ->call('save')
        ->assertHasNoErrors();

    expect((bool) $this->pilot->refresh()->opt_in)->toBeFalse();
});

it('keeps the password when the fields are blank and changes it when given', function (): void {
    $hash = $this->pilot->password;

    Livewire::test(ProfileEdit::class)->call('save')->assertHasNoErrors();
    expect($this->pilot->refresh()->password)->toBe($hash);

    Livewire::test(ProfileEdit::class)
        ->set('form.password', 'n3w-Passw0rd!')
        ->set('form.password_confirmation', 'n3w-Passw0rd!')
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check('n3w-Passw0rd!', $this->pilot->refresh()->password))->toBeTrue();
});

it('shows the rule errors under the fields', function (): void {
    $other = User::factory()->create();
    $field = UserField::query()->create(['name' => 'Callsign', 'internal' => false, 'active' => true, 'show_on_registration' => true, 'required' => true]);

    Livewire::test(ProfileEdit::class)
        ->set('form.name', '')
        ->set('form.email', strtoupper($other->email))
        ->set('form.password', 'n3w-Passw0rd!')
        ->set('form.password_confirmation', 'different')
        ->set('form.fields.field_'.$field->slug, '')
        ->call('save')
        ->assertHasErrors(['form.name' => 'required', 'form.email' => 'unique', 'form.password' => 'confirmed', 'form.fields.field_'.$field->slug => 'required'])
        ->assertNoRedirect();

    expect($this->pilot->refresh()->email)->not->toBe($other->email);
});

it('stores an uploaded avatar through UpdateProfile', function (): void {
    $target = public_path('uploads/avatars/'.$this->pilot->ident.'.png');
    File::ensureDirectoryExists(dirname($target));
    File::delete($target);

    Livewire::test(ProfileEdit::class)
        ->set('form.avatar', UploadedFile::fake()->image('me.png', 200, 200))
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('form.avatar', null);

    try {
        expect($this->pilot->refresh()->getRawOriginal('avatar'))->toBe('avatars/'.$this->pilot->ident.'.png')
            ->and(File::exists($target))->toBeTrue();
    } finally {
        File::delete($target);
    }
})->skip(!extension_loaded('gd'), 'GD is not available');

it('rejects an avatar that is not a jpeg or png when saving', function (): void {
    Livewire::test(ProfileEdit::class)
        ->set('form.avatar', UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))
        ->call('save')
        ->assertHasErrors(['form.avatar'])
        ->assertNoRedirect();
});

it('does not search for fewer than two characters', function (): void {
    Airport::factory()->create(['icao' => 'KJFK', 'name' => 'John F Kennedy']);

    $component = Livewire::test(ProfileEdit::class)->set('airportSearch', 'K');

    expect($component->instance()->airportResults)->toBe([]);
});

it('searches airports from two characters and picks one as the home airport', function (): void {
    $airport = Airport::factory()->create(['icao' => 'KJFK', 'name' => 'John F Kennedy']);
    Airport::factory()->create(['icao' => 'EGLL', 'name' => 'Heathrow']);

    $component = Livewire::test(ProfileEdit::class)->set('airportSearch', 'KJ');

    expect(collect($component->instance()->airportResults)->pluck('label')->all())->toBe(['KJFK - John F Kennedy']);

    $component
        ->call('chooseAirport', $airport->id)
        ->assertSet('form.home_airport_id', $airport->id)
        ->assertSet('airportLabel', 'KJFK - John F Kennedy')
        ->assertSet('airportSearch', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->pilot->refresh()->home_airport_id)->toBe($airport->id);
});

it('limits the search to hubs when pilots.home_hubs_only is on', function (): void {
    updateSetting('pilots.home_hubs_only', true);
    Airport::factory()->create(['icao' => 'KHUB', 'name' => 'Hub Field', 'hub' => true]);
    Airport::factory()->create(['icao' => 'KOUT', 'name' => 'Hub Outstation', 'hub' => false]);

    $component = Livewire::test(ProfileEdit::class)->set('airportSearch', 'Hub');

    expect(collect($component->instance()->airportResults)->pluck('label')->all())->toBe(['KHUB - Hub Field']);
});

it('clears the home airport', function (): void {
    $airport = Airport::factory()->create();
    $this->pilot->update(['home_airport_id' => $airport->id]);

    Livewire::test(ProfileEdit::class)
        ->assertSet('form.home_airport_id', $airport->id)
        ->call('clearAirport')
        ->assertSet('form.home_airport_id', null);
});

it('refuses a guest', function (): void {
    auth()->logout();

    Livewire::test(ProfileEdit::class)->assertForbidden();
});

it('deletes the previous avatar from public uploads when a new one is stored', function (): void {
    $target = public_path('uploads/avatars/'.$this->pilot->ident.'.png');
    $previous = public_path('uploads/avatars/'.$this->pilot->ident.'.jpg');
    File::ensureDirectoryExists(dirname($previous));
    File::put($previous, 'old');
    $this->pilot->forceFill(['avatar' => 'avatars/'.$this->pilot->ident.'.jpg'])->save();

    Livewire::test(ProfileEdit::class)
        ->set('form.avatar', UploadedFile::fake()->image('me.png', 200, 200))
        ->call('save')
        ->assertHasNoErrors();

    try {
        expect(File::exists($previous))->toBeFalse();
    } finally {
        File::delete([$previous, $target]);
    }
})->skip(!extension_loaded('gd'), 'GD is not available');

it('writes a resized avatar to public uploads with the real image manager', function (): void {
    $target = public_path('uploads/avatars/'.$this->pilot->ident.'.png');
    File::ensureDirectoryExists(dirname($target));
    File::delete($target);

    Livewire::test(ProfileEdit::class)
        ->set('form.avatar', UploadedFile::fake()->image('me.png', 400, 300))
        ->call('save')
        ->assertHasNoErrors();

    try {
        expect(File::exists($target))->toBeTrue();
        [$width, $height] = getimagesize($target);
        expect($width)->toBe((int) config('phpvms.avatar.width'))
            ->and($height)->toBe((int) config('phpvms.avatar.height'));
    } finally {
        File::delete($target);
    }
})->skip(!extension_loaded('gd'), 'GD is not available');
