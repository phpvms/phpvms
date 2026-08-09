<?php

declare(strict_types=1);

use App\Models\Airline;
use App\Models\Setting;
use App\Models\User;

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
 * it cannot read -- the settings table is empty during install, and the first
 * airline is created there. initJournal() declares a 'USD' default, but a
 * default only applies when the argument is omitted; passing the bare lookup
 * hands an explicit null to a string parameter and the install dies with
 * "Argument #1 ($currency_code) must be of type string, null given".
 *
 * tests/Pest.php seeds SettingsSeeder before every test, so the empty-settings
 * state these two cover is otherwise unreachable from the suite.
 */
test('a model with a journal can be created before units.currency is seeded', function (): void {
    Setting::query()->delete();

    $airline = Airline::factory()->create();

    expect($airline->journal)->not->toBeNull()
        ->and($airline->journal->currency)->toBe('USD');
});

test('users are also covered, not just airlines', function (): void {
    Setting::query()->delete();

    // Airline and User are the only two models using the trait, and the
    // installer creates both back to back.
    $user = User::factory()->create();

    expect($user->journal)->not->toBeNull()
        ->and($user->journal->currency)->toBe('USD');
});

test('a seeded units.currency still wins over the fallback', function (): void {
    // Settings are already seeded by tests/Pest.php, so this is the normal
    // path: the fallback must not shadow a value that is actually present.
    // Matched on `key` -- the primary key stores dots as underscores
    // (`units_currency`), which SettingService normalises on lookup.
    Setting::where('key', 'units.currency')->update(['value' => 'EUR']);

    expect(Airline::factory()->create()->journal->currency)->toBe('EUR');
});
