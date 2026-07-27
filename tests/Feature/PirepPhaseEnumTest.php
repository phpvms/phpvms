<?php

use App\Enums\PirepPhase;
use App\Enums\PirepStatus;
use App\Http\Resources\PirepResource;
use App\Models\Pirep;
use Illuminate\Support\Facades\DB;

test('the old name resolves to the new enum rather than a parallel one', function (): void {
    // ::class is resolved by the compiler, so reflection is what follows the alias.
    expect(new ReflectionClass(PirepStatus::class)->getName())->toBe(PirepPhase::class);

    // Not toEqual: a copied enum would compare equal and still be a different type.
    expect(PirepStatus::TAXI)->toBe(PirepPhase::TAXI)
        ->and(PirepStatus::cases())->toBe(PirepPhase::cases());
});

test('type checks against the old name pass for cases of the new enum', function (): void {
    $phase = PirepPhase::ENROUTE;

    expect($phase)->toBeInstanceOf(PirepStatus::class)
        ->and(PirepStatus::ENROUTE)->toBeInstanceOf(PirepPhase::class);

    // Keeps addon signatures typed against the old name working.
    $takesOldName = fn (PirepStatus $p): string => $p->value;
    expect($takesOldName(PirepPhase::LANDED))->toBe('LAN');
});

test('phase values stored before the rename read back to the same cases', function (): void {
    $pirep = Pirep::factory()->create();

    // The raw code as a pre-rename install holds it, bypassing the cast.
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
