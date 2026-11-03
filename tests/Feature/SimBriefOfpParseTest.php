<?php

declare(strict_types=1);

use App\Support\Dto\SimBriefOfp\SimBriefOfp;

/*
 * SimBrief omits keys it has no value for. A missing optional field must
 * parse as null instead of failing the whole OFP.
 */

it('parses an OFP whose FIR NOTAM has no icao_name', function (): void {
    $ofp = json_decode(readDataFile('simbrief/briefing.json'), true, flags: JSON_THROW_ON_ERROR);
    unset($ofp['notams'][0]['icao_name']);

    $parsed = SimBriefOfp::from($ofp);

    expect($parsed->notams[0]->icao_name)->toBeNull()
        ->and($parsed->notams[0]->notam_id)->toBe($ofp['notams'][0]['notam_id']);
});

it('keeps the decimals of the average passenger and bag weights', function (): void {
    $ofp = json_decode(readDataFile('simbrief/briefing.json'), true, flags: JSON_THROW_ON_ERROR);
    $ofp['weights']['pax_weight'] = '85.729';
    $ofp['weights']['bag_weight'] = '24.948';

    $weights = SimBriefOfp::from($ofp)->weights;

    expect($weights->pax_weight)->toBe(85.729)
        ->and($weights->bag_weight)->toBe(24.948);
});

it('parses an ATIS that SimBrief sends without an issue time', function (): void {
    $ofp = json_decode(readDataFile('simbrief/briefing.json'), true, flags: JSON_THROW_ON_ERROR);
    $atis = ['network' => 'vatsim', 'letter' => 'T', 'phonetic' => 'TANGO', 'type' => 'DEP', 'message' => 'INFORMATION T'];
    $ofp['destination']['atis'] = [
        [...$atis, 'issued' => []],
        [...$atis, 'issued' => '2026-10-27T21:00:00Z'],
    ];

    $parsed = SimBriefOfp::from($ofp)->destination->atis;

    expect($parsed[0]->issued)->toBeNull()
        ->and($parsed[1]->issued?->toIso8601ZuluString())->toBe('2026-10-27T21:00:00Z');
});

it('parses an ATIS that SimBrief sends without a letter', function (): void {
    $ofp = json_decode(readDataFile('simbrief/briefing.json'), true, flags: JSON_THROW_ON_ERROR);
    $ofp['destination']['atis'] = [
        ['network' => 'vatsim', 'issued' => '2026-10-29T20:30:00Z', 'letter' => [], 'phonetic' => [], 'type' => [], 'message' => []],
    ];

    $parsed = SimBriefOfp::from($ofp)->destination->atis[0];

    expect($parsed->letter)->toBe('')
        ->and($parsed->phonetic)->toBe('')
        ->and($parsed->type)->toBe('')
        ->and($parsed->message)->toBe('');
});

it('parses a take-off and an en-route alternate SimBrief sends on their own, not in a list', function (): void {
    $ofp = json_decode(readDataFile('simbrief/briefing.json'), true, flags: JSON_THROW_ON_ERROR);
    $ofp['takeoff_altn'] = $ofp['origin'];
    $ofp['enroute_altn'] = $ofp['destination'];

    $parsed = SimBriefOfp::from($ofp);

    expect($parsed->takeoff_altn)->toHaveCount(1)
        ->and($parsed->takeoff_altn[0]->icao_code)->toBe($ofp['origin']['icao_code'])
        ->and($parsed->enroute_altn)->toHaveCount(1)
        ->and($parsed->enroute_altn[0]->icao_code)->toBe($ofp['destination']['icao_code']);
});

it('parses a lone alternate SimBrief sends on its own, not in a list', function (): void {
    $ofp = json_decode(readDataFile('simbrief/briefing.json'), true, flags: JSON_THROW_ON_ERROR);
    $ofp['alternate'] = $ofp['alternate'][0];

    $parsed = SimBriefOfp::from($ofp);

    expect($parsed->alternate)->toHaveCount(1)
        ->and($parsed->alternate[0]->icao_code)->toBe($ofp['alternate']['icao_code']);
});

it('parses a list of take-off alternates', function (): void {
    $ofp = json_decode(readDataFile('simbrief/briefing.json'), true, flags: JSON_THROW_ON_ERROR);
    $ofp['takeoff_altn'] = [$ofp['origin'], $ofp['destination']];

    expect(SimBriefOfp::from($ofp)->takeoff_altn)->toHaveCount(2);
});
