<?php

declare(strict_types=1);

use App\Features\Setup\Actions\RunPostUpgradeTasks;
use App\Features\Setup\Enums\PostUpgradeStatus;
use App\Features\Setup\PostUpgrade\ConvertSimBriefOfps;
use App\Features\Setup\PostUpgrade\SimBriefXmlToJson;
use App\Features\Setup\Support\PostUpgradeTaskState;
use App\Models\SimBrief;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A row as 7.x left it: the OFP in ofp_xml, no JSON file.
 */
function legacySimbriefRow(string $id, string $xml): void
{
    DB::table('simbrief')->insert([
        'id'            => $id,
        'user_id'       => 1,
        'ofp_xml'       => $xml,
        'acars_xml'     => '<flightplan/>',
        'ofp_json_path' => null,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);
}

function legacyOfpXml(): string
{
    return simbriefOfpXml(json_decode(readDataFile('simbrief/briefing.json'), true, flags: JSON_THROW_ON_ERROR));
}

beforeEach(function (): void {
    Storage::fake();
});

test('converts a 7.x OFP into the JSON file 8.x reads and keeps the XML', function (): void {
    $xml = legacyOfpXml();
    legacySimbriefRow('1784000000_AAAAAAAAAA', $xml);

    app(RunPostUpgradeTasks::class)->handle();

    $simbrief = SimBrief::findOrFail('1784000000_AAAAAAAAAA');
    expect($simbrief->ofp_json_path)->toBe('simbrief/1784000000_AAAAAAAAAA.json')
        ->and($simbrief->ofp->general->flight_number)->toBe('112')
        ->and($simbrief->ofp->times->est_time_enroute)->toBe('00:37:06')
        ->and($simbrief->files)->not->toBeEmpty()
        ->and($simbrief->xml->params->units)->toBe('kgs')
        ->and(DB::table('simbrief')->where('id', $simbrief->id)->value('ofp_xml'))->toBe($xml)
        ->and(app(RunPostUpgradeTasks::class)->status(ConvertSimBriefOfps::KEY))->toBe(PostUpgradeStatus::Done);
});

test('skips an OFP that will not convert in the same run and converts the rest', function (): void {
    app()->bind(ConvertSimBriefOfps::class, fn (): ConvertSimBriefOfps => new ConvertSimBriefOfps(app(PostUpgradeTaskState::class), new SimBriefXmlToJson(), chunkSize: 2));
    legacySimbriefRow('a', legacyOfpXml());
    legacySimbriefRow('b', '<OFP><params>');
    legacySimbriefRow('c', legacyOfpXml());

    app(RunPostUpgradeTasks::class)->handle();

    $data = app(ConvertSimBriefOfps::class)->load();
    expect(DB::table('simbrief')->whereNotNull('ofp_json_path')->orderBy('id')->pluck('id')->all())->toBe(['a', 'c'])
        ->and($data['skipped'])->toBe(['b'])
        ->and(app(ConvertSimBriefOfps::class)->progress())->toBe(['done' => 3, 'total' => 3])
        ->and(DB::table('simbrief')->where('id', 'b')->value('ofp_xml'))->toBe('<OFP><params>')
        ->and(app(RunPostUpgradeTasks::class)->status(ConvertSimBriefOfps::KEY))->toBe(PostUpgradeStatus::Done);
});

test('an OFP 8.x could not read is skipped, not linked', function (): void {
    $ofp = json_decode(readDataFile('simbrief/briefing.json'), true, flags: JSON_THROW_ON_ERROR);
    unset($ofp['general']);
    legacySimbriefRow('nogeneral', simbriefOfpXml($ofp));

    app(RunPostUpgradeTasks::class)->handle();

    expect(DB::table('simbrief')->where('id', 'nogeneral')->value('ofp_json_path'))->toBeNull()
        ->and(Storage::exists('simbrief/nogeneral.json'))->toBeFalse()
        ->and(app(ConvertSimBriefOfps::class)->load()['skipped'])->toBe(['nogeneral']);
});

test('a site with no 7.x OFPs has nothing to convert', function (): void {
    SimBrief::factory()->create(['user_id' => 1, 'ofp_json_path' => null]);

    expect(app(ConvertSimBriefOfps::class)->pending())->toBeFalse();
});
