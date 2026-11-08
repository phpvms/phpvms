<?php

declare(strict_types=1);

use App\Events\ProfileUpdated;
use App\Features\Profile\Actions\RegenerateApiKey;
use App\Features\Profile\Actions\UpdateProfile;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

it('updates a profile from validated values, without a request', function (): void {
    Event::fake([ProfileUpdated::class]);
    $user = User::factory()->create();
    $field = UserField::query()->create(['name' => 'Favourite Type', 'internal' => false, 'active' => true]);

    app(UpdateProfile::class)->handle(
        $user,
        ['name' => 'New Name', 'email' => $user->email, 'airline_id' => $user->airline_id, 'password' => 'n3w-Passw0rd!', 'opt_in' => true],
        null,
        ['field_'.$field->slug => 'B738'],
    );

    $user->refresh();
    expect($user->name)->toBe('New Name')
        ->and(Hash::check('n3w-Passw0rd!', $user->password))->toBeTrue()
        ->and($user->opt_in)->toBeTruthy()
        ->and(UserFieldValue::query()->where(['user_id' => $user->id, 'user_field_id' => $field->id])->value('value'))->toBe('B738');
    Event::assertDispatched(ProfileUpdated::class, fn (ProfileUpdated $event): bool => $event->user->is($user));
});

it('keeps the password when none is given', function (): void {
    $user = User::factory()->create();
    $hash = $user->password;

    app(UpdateProfile::class)->handle($user, ['name' => $user->name, 'email' => $user->email, 'airline_id' => $user->airline_id, 'password' => null], null, []);

    expect($user->refresh()->password)->toBe($hash);
});

it('regenerates the legacy API key', function (): void {
    $user = User::factory()->create();
    $old = $user->api_key;

    $new = app(RegenerateApiKey::class)->handle($user);

    expect($new)->not->toBe($old)
        ->and($user->refresh()->api_key)->toBe($new);
});
