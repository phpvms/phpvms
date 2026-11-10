<?php

declare(strict_types=1);

use App\Models\Airline;
use App\Models\User;
use App\Services\SettingService;

/*
 * Regression coverage for JournalTrait::initJournal().
 *
 * The `!$this->journal` guard inside initJournal() lazy-loads the journal
 * relation and caches it as null. Saving the new journal through the relation
 * does not refresh that cache, so the creating model previously kept returning
 * null for ->journal until it was reloaded from the database.
 */

test('initJournal populates the journal relation on the same instance', function (): void {
    // Creating a model that uses JournalTrait fires the `created` hook, which
    // calls initJournal(). The freshly-created instance must expose the journal
    // immediately, without a reload from the database.
    $airline = Airline::factory()->create();

    expect($airline->relationLoaded('journal'))->toBeTrue()
        ->and($airline->journal)->not->toBeNull()
        ->and($airline->journal->exists)->toBeTrue();

    // The cached relation must be the row that was actually persisted, i.e. the
    // same journal a fresh reload from the database resolves to.
    expect($airline->journal->id)->toBe($airline->fresh()->journal->id);
});

/*
 * The `created` hook reads units.currency, and setting() returns null for a key
 * it cannot read -- the settings table may not be readable yet during install,
 * and the first airline is created there. initJournal() declares a 'USD'
 * default, but a default only applies when the argument is omitted; passing the
 * bare lookup hands an explicit null to a string parameter and the install dies
 * with "Argument #1 ($currency_code) must be of type string, null given".
 *
 * An empty settings table reads as the class defaults, so these two make the
 * settings unreadable instead.
 */
function makeSettingsUnreadable(): void
{
    test()->mock(SettingService::class)
        ->shouldReceive('retrieve')
        ->andThrow(new RuntimeException('settings unreadable'));
}

test('a model with a journal can be created while settings cannot be read', function (): void {
    makeSettingsUnreadable();

    $airline = Airline::factory()->create();

    expect($airline->journal)->not->toBeNull()
        ->and($airline->journal->currency)->toBe('USD');
});

test('users are also covered, not just airlines', function (): void {
    makeSettingsUnreadable();

    // Airline and User are the only two models using the trait, and the
    // installer creates both back to back.
    $user = User::factory()->create();

    expect($user->journal)->not->toBeNull()
        ->and($user->journal->currency)->toBe('USD');
});

test('a seeded units.currency still wins over the fallback', function (): void {
    // The normal path: the fallback must not shadow a value that is
    // actually present.
    updateSetting('units.currency', 'EUR');

    expect(Airline::factory()->create()->journal->currency)->toBe('EUR');
});
