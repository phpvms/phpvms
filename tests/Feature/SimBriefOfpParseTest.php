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
