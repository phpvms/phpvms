<?php

declare(strict_types=1);

use App\Support\PirepLevelNormalizer;
use Illuminate\Support\Facades\Log;

test('a flight-level value passes through untouched', function (): void {
    expect(PirepLevelNormalizer::coerce(350.0))->toEqual(350)
        ->and(PirepLevelNormalizer::coerce(20.0))->toEqual(20)
        ->and(PirepLevelNormalizer::coerce(400.0))->toEqual(400);
});

test('a feet value is coerced to the nearest flight level', function (): void {
    expect(PirepLevelNormalizer::coerce(35000.0))->toEqual(350)
        ->and(PirepLevelNormalizer::coerce(2000.0))->toEqual(20)
        ->and(PirepLevelNormalizer::coerce(40000.0))->toEqual(400);
});

test('coercion rounds to the nearest flight level rather than truncating', function (): void {
    // 35,049 ft / 100 = 350.49 -> rounds to 350, not 351 or 350 by truncation.
    expect(PirepLevelNormalizer::coerce(35049.0))->toEqual(350)
        // 35,051 ft / 100 = 350.51 -> rounds to 351.
        ->and(PirepLevelNormalizer::coerce(35051.0))->toEqual(351);
});

test('a boundary value behaves as documented: <= 1000 is a flight level, > 1000 is feet', function (): void {
    // Exactly at the ceiling: still a flight level (an absurd one, but the
    // rule is a strict ">", not ">=" -- documented on PirepLevelNormalizer).
    expect(PirepLevelNormalizer::coerce(1000.0))->toEqual(1000);

    // One unit past it: treated as feet.
    expect(PirepLevelNormalizer::coerce(1001.0))->toEqual(10);
});

test('normalize() leaves attrs without a level key untouched', function (): void {
    $attrs = ['dpt_airport_id' => 'KJFK'];

    expect(PirepLevelNormalizer::normalize($attrs))->toEqual($attrs);
});

test('normalize() leaves a null level untouched -- null still works', function (): void {
    $attrs = ['level' => null];

    expect(PirepLevelNormalizer::normalize($attrs))->toEqual(['level' => null]);
});

test('normalize() leaves a non-numeric level for validation to reject, not silently coerced', function (): void {
    $attrs = ['level' => ''];

    expect(PirepLevelNormalizer::normalize($attrs))->toEqual(['level' => '']);

    $attrs = ['level' => 'not-a-number'];

    expect(PirepLevelNormalizer::normalize($attrs))->toEqual(['level' => 'not-a-number']);
});

test('normalize() coerces a feet-valued level in an attrs array', function (): void {
    $attrs = ['level' => 35000, 'dpt_airport_id' => 'KJFK'];

    expect(PirepLevelNormalizer::normalize($attrs))->toEqual(['level' => 350, 'dpt_airport_id' => 'KJFK']);
});

test('normalize() leaves an already-correct flight level untouched in an attrs array', function (): void {
    $attrs = ['level' => 350];

    expect(PirepLevelNormalizer::normalize($attrs))->toEqual(['level' => 350]);
});

test('normalize() accepts a numeric string, matching the "numeric" validation rule it runs after', function (): void {
    expect(PirepLevelNormalizer::normalize(['level' => '35000']))->toEqual(['level' => 350])
        ->and(PirepLevelNormalizer::normalize(['level' => '350']))->toEqual(['level' => 350]);
});

test('normalize() logs when it coerces a value', function (): void {
    Log::spy();

    PirepLevelNormalizer::normalize(['level' => 35000]);

    Log::shouldHaveReceived('warning')->once();
});

test('normalize() does not log when the value already looked like a flight level', function (): void {
    Log::spy();

    PirepLevelNormalizer::normalize(['level' => 350]);

    Log::shouldNotHaveReceived('warning');
});
