<?php

use App\Enums\PirepPhase;
use App\Enums\PirepStatus;
use App\Http\Resources\PirepResource;
use App\Models\Pirep;
use Illuminate\Support\Facades\DB;

test('the old name resolves to the new enum rather than a parallel one', function (): void {
    // `PirepStatus::class` is resolved by the compiler and is just the literal
    // string, so it proves nothing on its own. Reflection is what actually
    // follows the alias to the class behind it.
    expect(new ReflectionClass(PirepStatus::class)->getName())->toBe(PirepPhase::class);

    // Not toEqual: a copied enum would compare equal case-by-case and still be a
    // different type. Identity is the only assertion that distinguishes an alias
    // from a duplicate.
    expect(PirepStatus::TAXI)->toBe(PirepPhase::TAXI)
        ->and(PirepStatus::cases())->toBe(PirepPhase::cases());
});

test('type checks against the old name pass for cases of the new enum', function (): void {
    $phase = PirepPhase::ENROUTE;

    expect($phase)->toBeInstanceOf(PirepStatus::class)
        ->and(PirepStatus::ENROUTE)->toBeInstanceOf(PirepPhase::class);

    // A parameter typed against the old name accepts a value produced under the
    // new one, which is what keeps addon signatures working untouched.
    $takesOldName = fn (PirepStatus $p): string => $p->value;
    expect($takesOldName(PirepPhase::LANDED))->toBe('LAN');
});

test('phase values stored before the rename read back to the same cases', function (): void {
    $pirep = Pirep::factory()->create();

    // Write the raw three-character code the way a pre-rename install holds it,
    // bypassing the cast entirely.
    DB::table('pireps')->where('id', $pirep->id)->update(['status' => 'ENR']);

    $reloaded = Pirep::find($pirep->id);

    expect($reloaded->status)->toBe(PirepPhase::ENROUTE)
        ->and($reloaded->status)->toBe(PirepStatus::ENROUTE)
        ->and($reloaded->status->value)->toBe('ENR');
});

test('the API still publishes the value under phase', function (): void {
    $pirep = Pirep::factory()->create(['status' => PirepPhase::ENROUTE]);

    $res = PirepResource::make($pirep)->toArray(request());

    expect($res)->toHaveKey('phase')
        ->and($res['phase'])->toBe(PirepPhase::ENROUTE)
        ->and($res)->not->toHaveKey('status_enum');
});
