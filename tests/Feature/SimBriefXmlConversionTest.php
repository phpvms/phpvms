<?php

declare(strict_types=1);

use App\Features\Setup\PostUpgrade\SimBriefXmlToJson;
use App\Support\Dto\SimBriefOfp\SimBriefOfp;
use Illuminate\Support\Arr;

function simbriefJsonFixture(): array
{
    return json_decode(readDataFile('simbrief/briefing.json'), true, flags: JSON_THROW_ON_ERROR);
}

it('turns the XML of a v2 OFP back into that OFP', function (): void {
    $json = simbriefJsonFixture();

    $converted = new SimBriefXmlToJson()->convert(simbriefOfpXml($json));

    // The XML has no fetch block, so its timing is not recoverable.
    expect(Arr::except($converted, 'fetch.time'))->toBe(Arr::except($json, 'fetch.time'))
        ->and(SimBriefOfp::from($converted)->text->plan_html)->toBe($json['text']['plan_html']);
});

it('converts the 7.x fixture XML into the v2 fields 8.x and acars read', function (): void {
    $xml = simplexml_load_string(readDataFile('simbrief/briefing.xml'));
    $json = simbriefJsonFixture();

    $converted = new SimBriefXmlToJson()->convert(readDataFile('simbrief/briefing.xml'));

    expect($converted['params']['time_generated'])->toBe(gmdate('Y-m-d\TH:i:s\Z', (int) $xml->params->time_generated))
        ->and($converted['params']['units'])->toBe('lbs')
        ->and($converted['times']['est_time_enroute'])->toBe(gmdate('H:i:s', (int) $xml->times->est_time_enroute))
        ->and($converted['times']['sched_out'])->toBe(gmdate('Y-m-d\TH:i:s\Z', (int) $xml->times->sched_out))
        // Same airports as the JSON fixture, so the same offsets.
        ->and($converted['times']['orig_timezone'])->toBe($json['times']['orig_timezone'])
        ->and($converted['times']['dest_timezone'])->toBe($json['times']['dest_timezone'])
        ->and($converted['general']['dx_rmk'])->toBe(['PLANNED OPTIMUM FLIGHT LEVEL', 'PAYLOAD/CARGO LIMITED BY MLW'])
        ->and($converted['general']['sys_rmk'])->toBe([])
        ->and($converted['general']['cruise_mach'])->toBe('.54')
        ->and($converted['origin']['faa_code'])->toBe('')
        ->and($converted['alternate'])->toHaveCount(1)
        ->and($converted['alternate'][0]['icao_code'])->toBe('OTHH')
        ->and($converted['navlog'][0]['ident'])->toBe((string) $xml->navlog->fix[0]->ident)
        ->and($converted['navlog'][0]['time_leg'])->toBe(gmdate('H:i:s', (int) $xml->navlog->fix[0]->time_leg))
        ->and($converted['text']['plan_html'])->toStartWith('<div style="line-height:14px')
        ->and(array_column($converted['images']['map'], 'link'))->toBe(array_map(strval(...), $xml->xpath('/OFP/images/map/link')))
        ->and(array_column($converted['files']['file'], 'name'))->toHaveCount(count($xml->files->file))
        ->and($converted['files']['pdf']['link'])->toBe($json['files']['pdf']['link']);

    // The 2020 XML predates fields 8.x requires: the JSON fixture fills those, the XML's sections override it.
    $sections = ['params', 'general', 'origin', 'destination', 'alternate', 'navlog', 'times', 'fuel', 'weights', 'text', 'files', 'fms_downloads', 'images'];
    $ofp = SimBriefOfp::from(array_replace_recursive($json, Arr::only($converted, $sections)));

    expect($ofp->params->units)->toBe('lbs')
        ->and($ofp->times->est_time_enroute)->toBe($converted['times']['est_time_enroute'])
        ->and($ofp->general->dx_rmk)->toBe($converted['general']['dx_rmk'])
        ->and($ofp->fuel->plan_ramp)->toBe((int) $xml->fuel->plan_ramp)
        ->and($ofp->weights->payload)->toBe((int) $xml->weights->payload);
});

it('refuses XML that is not a SimBrief OFP', function (string $xml): void {
    expect(fn (): array => new SimBriefXmlToJson()->convert($xml))->toThrow(RuntimeException::class);
})->with([
    'not XML'      => ['<OFP><params>'],
    'another root' => ['<?xml version="1.0"?><flight/>'],
    'empty'        => [''],
]);
