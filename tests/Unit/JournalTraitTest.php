<?php

declare(strict_types=1);

use App\Models\Airline;

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
