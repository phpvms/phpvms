<?php

declare(strict_types=1);

use App\Contracts\Model;
use App\Models\File;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Trait Testing: HasNanoIds
|--------------------------------------------------------------------------
|
| The model-level tests use the File model, but they verify the global
| behavior of the HasNanoIds trait. Any model using the trait—File, Flight,
| Pirep, Acars—gets the same Nano ID primary key handling.
|
*/

it('generates a nano id at the configured length and alphabet', function (): void {
    $id = Str::nanoid();

    expect($id)
        ->toBeString()
        ->and(strlen($id))->toBe(Model::ID_MAX_LENGTH)
        ->and($id)->toMatch('/^['.Model::ID_ALPHABET.']+$/');
});

it('honors a custom nano id length', function (): void {
    expect(strlen(Str::nanoid(24)))->toBe(24);
});

it('validates nano ids', function (): void {
    expect(Str::isNanoid(Str::nanoid()))->toBeTrue()
        ->and(Str::isNanoid('UPPER'.Str::nanoid(11)))->toBeFalse() // out of alphabet
        ->and(Str::isNanoid(Str::nanoid(8)))->toBeFalse()          // wrong length
        ->and(Str::isNanoid(123))->toBeFalse();                    // non-string
});

it('assigns a nano id primary key on create', function (): void {
    $file = File::factory()->create();

    expect(Str::isNanoid($file->id))->toBeTrue();
});

it('preserves an explicitly set primary key', function (): void {
    $file = File::factory()->create(['id' => 'mycustomid000001']);

    expect($file->id)->toBe('mycustomid000001');
});

it('reports a non-incrementing string key', function (): void {
    $file = new File();

    expect($file->getKeyType())->toBe('string')
        ->and($file->getIncrementing())->toBeFalse();
});

it('returns null for an unknown id during route-model binding', function (): void {
    // Route binding no longer rejects keys purely on Nano ID format — the
    // database decides existence. An id that matches no row resolves to null
    // (a 404 over HTTP), it does not throw.
    expect(new File()->resolveRouteBinding('not a valid id!'))->toBeNull();
});

it('resolves a valid nano id during route-model binding', function (): void {
    $file = File::factory()->create();

    $resolved = new File()->resolveRouteBinding($file->id);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($file->id);
});

it('resolves a legacy non-format primary key during route-model binding', function (): void {
    // Regression guard: tables still hold pre-migration keys (v7 UUIDs, older
    // Nano IDs with a different alphabet/length) that fail Str::isNanoid().
    // These are valid rows and must still resolve — the strict format gate used
    // to 404 every one of them (e.g. admin PIREP view/edit links).
    $legacyId = 'fc4b672f-7a5d-4a2b-a0ee-0e4b74a3c143';
    $file = File::factory()->create(['id' => $legacyId]);

    expect(Str::isNanoid($legacyId))->toBeFalse();

    $resolved = new File()->resolveRouteBinding($file->id);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($legacyId);
});
