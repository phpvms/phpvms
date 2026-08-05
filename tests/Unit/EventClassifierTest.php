<?php

declare(strict_types=1);

use App\Enums\PirepPhase;
use App\Services\Pirep\EventClassifier;

test('classifies a regex rule', function (string $log, string $type, string $category, ?array $details): void {
    $result = EventClassifier::classify($log);

    expect($result['type'])->toBe($type)
        ->and($result['category'])->toBe($category)
        ->and($result['phase'])->toBeNull()
        ->and($result['details'])->toBe($details);
})->with([
    'engine on'              => ['Engine 2 is on', 'engine-start', 'aircraft', ['engine_number' => 2, 'state' => true]],
    'engine off'             => ['Engine 2 is off', 'engine-stop', 'aircraft', ['engine_number' => 2, 'state' => false]],
    'parking brake set'      => ['Parking brake set', 'parking-brake-set', 'aircraft', ['feature' => 'ParkingBrakes', 'state' => true]],
    'parking brake released' => ['Parking brake released', 'parking-brake-released', 'aircraft', ['feature' => 'ParkingBrakes', 'state' => false]],
    'gear up'                => ['Landing Gear set to up', 'gear-up', 'aircraft', ['feature' => 'LandingGear', 'state' => true]],
    'gear down'              => ['Landing Gear set to down', 'gear-down', 'aircraft', ['feature' => 'LandingGear', 'state' => false]],
    'flaps change'           => ['Flaps set to 15', 'flaps-change', 'aircraft', ['flaps' => '15']],
    'transponder'            => ['Transponder changed to 1200', 'transponder-change', 'systems', ['squawk' => 1200]],
    'sim rate'               => ['Sim rate increased to 4x', 'sim-rate-change', 'systems', ['sim_rate' => 4.0]],
    'top of climb'           => ['TOC reached', 'top-of-climb', 'milestone', null],
    'top of descent'         => ['Top of descent reached', 'top-of-descent', 'milestone', null],
    'min altitude'           => ['Reached flight minimum altitude', 'min-altitude', 'milestone', null],
    'runway cross'           => ['On or crossing runway 09L', 'runway-cross', 'milestone', ['runway' => '09L']],
    'unlimited fuel'         => ['Change to unlimited fuel setting: on', 'unlimited-fuel-toggle', 'systems', ['state' => true]],
    'generic feature toggle' => ['Beacon Lights set to on', 'feature-toggle', 'aircraft', ['feature' => 'Beacon Lights', 'state' => true]],
]);

test('classifies rule violations', function (string $log, int $count, int $points): void {
    $result = EventClassifier::classify($log);

    expect($result['type'])->toBe('rule-violation')
        ->and($result['category'])->toBe('violation')
        ->and($result['phase'])->toBeNull()
        ->and($result['details'])->toBe([
            'points'    => $points,
            'count'     => $count,
            'rule_name' => 'Overspeed',
        ]);
})->with([
    'with count'      => ['Rule Triggered - Overspeed (2x), 10pts', 2, 10],
    'without count'   => ['Rule Triggered - Overspeed, 10pts', 1, 10],
    'negative points' => ['Rule Triggered - Overspeed, -10pts', 1, -10],
]);

test('classifies phase-transition literals', function (string $log, PirepPhase $phase): void {
    $result = EventClassifier::classify($log);

    expect($result['type'])->toBe('phase-change')
        ->and($result['category'])->toBe('phase')
        ->and($result['phase'])->toBe($phase->value)
        ->and($result['details'])->toBeNull();
})->with([
    'started boarding'  => ['Started boarding', PirepPhase::BOARDING],
    'started pushback'  => ['Started pushback', PirepPhase::PUSHBACK_TOW],
    'started taxi out'  => ['Started taxi out', PirepPhase::TAXI],
    'started takeoff'   => ['Started takeoff', PirepPhase::TAKEOFF],
    'on approach'       => ['On approach', PirepPhase::APPROACH_ICAO],
    'on final approach' => ['On final approach', PirepPhase::ON_FINAL],
    'landing rate'      => ['Landing rate: -120fpm', PirepPhase::LANDING],
    'blocks on time'    => ['Blocks on time', PirepPhase::ON_BLOCK],
]);

test('flaps set to up is a flaps-change event that also carries the enroute phase hint', function (): void {
    $result = EventClassifier::classify('Flaps set to up');

    expect($result['type'])->toBe('flaps-change')
        ->and($result['category'])->toBe('aircraft')
        ->and($result['phase'])->toBe(PirepPhase::ENROUTE->value)
        ->and($result['details'])->toBe(['flaps' => 'up']);
});

test('degrades safely for unrecognised or empty input', function (?string $log): void {
    expect(EventClassifier::classify($log))->toBe([
        'type'     => null,
        'category' => 'message',
        'phase'    => null,
        'details'  => null,
    ]);
})->with([
    'unrecognised string' => ['Something the classifier has never seen'],
    'null'                => [null],
    'empty string'        => [''],
    'whitespace only'     => ['   '],
]);
