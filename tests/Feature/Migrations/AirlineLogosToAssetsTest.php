<?php

declare(strict_types=1);

use App\Features\Assets\AssetService;
use App\Models\Airline;
use App\Models\Asset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function airlineLogosMigration(): object
{
    return require base_path('database/migrations_data/2026_08_17_000001_airline_logos_to_assets.php');
}

function airlinePublicDisk()
{
    return Storage::disk(config('filesystems.public_files'));
}

/** The column is no longer fillable, so seed the legacy value directly. */
function seedLegacyLogo(Airline $airline, string $logo): void
{
    DB::table('airlines')->where('id', $airline->id)->update(['logo' => $logo]);
}

beforeEach(function (): void {
    fakeAssetDisks();
});

it('adopts a hosted logo into the airline-logo slot and clears the column', function (): void {
    $airline = Airline::factory()->create(['icao' => 'ABC', 'iata' => 'AB', 'country' => 'us', 'logo' => null]);
    airlinePublicDisk()->put('airlines/1.webp', ASSET_TEST_PNG);
    $originalUrl = airlinePublicDisk()->url('airlines/1.webp');
    seedLegacyLogo($airline, 'airlines/1.webp');

    airlineLogosMigration()->up();

    $asset = app(AssetService::class)->find(Asset::SLOT_AIRLINE_LOGO, 'ABC');

    expect($asset)->not->toBeNull()
        ->and($asset->is_public)->toBeTrue()
        // Adopted, not copied: the URL every install has published still works.
        ->and($asset->path)->toBe('airlines/1.webp')
        ->and($asset->url())->toBe($originalUrl)
        // The asset is the single source of truth now.
        ->and(DB::table('airlines')->where('id', $airline->id)->value('logo'))->toBeNull();

    // One copy of the bytes, where it always was.
    expect(airlinePublicDisk()->allFiles())->toBe(['airlines/1.webp']);

    // And the accessor still resolves, so no consumer notices the move.
    expect($airline->refresh()->logo_url)->toBe($originalUrl);
});

/**
 * An external URL is not a file we host, so it cannot become an asset and must
 * be left exactly where it is.
 */
it('leaves an external logo URL on the column', function (): void {
    $airline = Airline::factory()->create(['icao' => 'XYZ', 'iata' => 'XY', 'country' => 'us', 'logo' => null]);
    seedLegacyLogo($airline, 'https://cdn.example.com/xyz.png');

    airlineLogosMigration()->up();

    expect(app(AssetService::class)->find(Asset::SLOT_AIRLINE_LOGO, 'XYZ'))->toBeNull()
        ->and(DB::table('airlines')->where('id', $airline->id)->value('logo'))->toBe('https://cdn.example.com/xyz.png')
        ->and($airline->refresh()->logo_url)->toBe('https://cdn.example.com/xyz.png');
});

/**
 * A column pointing at a file that has since been deleted has nothing to adopt.
 * Leaving the value alone keeps the legacy fallback working if it reappears.
 */
it('leaves a hosted logo whose file is gone', function (): void {
    $airline = Airline::factory()->create(['icao' => 'GON', 'iata' => 'GO', 'country' => 'us', 'logo' => null]);
    seedLegacyLogo($airline, 'airlines/missing.webp');

    airlineLogosMigration()->up();

    expect(app(AssetService::class)->find(Asset::SLOT_AIRLINE_LOGO, 'GON'))->toBeNull()
        ->and(DB::table('airlines')->where('id', $airline->id)->value('logo'))->toBe('airlines/missing.webp');
});

it('keys each airline to its own logo', function (): void {
    foreach (['AAA' => 'a', 'BBB' => 'b', 'CCC' => 'c'] as $icao => $marker) {
        $airline = Airline::factory()->create(['icao' => $icao, 'iata' => substr($icao, 0, 2), 'country' => 'us', 'logo' => null]);
        // Distinct bytes, so a mis-keyed adopt cannot pass.
        airlinePublicDisk()->put("airlines/{$marker}.webp", ASSET_TEST_PNG."\x00".$marker);
        seedLegacyLogo($airline, "airlines/{$marker}.webp");
    }

    airlineLogosMigration()->up();

    foreach (['AAA' => 'a', 'BBB' => 'b', 'CCC' => 'c'] as $icao => $marker) {
        $asset = app(AssetService::class)->find(Asset::SLOT_AIRLINE_LOGO, $icao);

        expect($asset)->not->toBeNull()
            ->and(Storage::disk($asset->diskName())->get($asset->path))->toBe(ASSET_TEST_PNG."\x00".$marker);
    }
});

it('is safe to run twice', function (): void {
    $airline = Airline::factory()->create(['icao' => 'ABC', 'iata' => 'AB', 'country' => 'us', 'logo' => null]);
    airlinePublicDisk()->put('airlines/1.webp', ASSET_TEST_PNG);
    seedLegacyLogo($airline, 'airlines/1.webp');

    airlineLogosMigration()->up();
    airlineLogosMigration()->up();

    expect(Asset::query()->count())->toBe(1);
    airlinePublicDisk()->assertExists('airlines/1.webp');
});

/**
 * down() points the column back at the adopted file and drops the row. The file
 * never moved, so it must still be there afterwards — deleting the asset
 * through the model would have taken it.
 */
it('restores the column and keeps the file on down', function (): void {
    $airline = Airline::factory()->create(['icao' => 'ABC', 'iata' => 'AB', 'country' => 'us', 'logo' => null]);
    airlinePublicDisk()->put('airlines/1.webp', ASSET_TEST_PNG);
    seedLegacyLogo($airline, 'airlines/1.webp');

    airlineLogosMigration()->up();
    airlineLogosMigration()->down();

    expect(DB::table('airlines')->where('id', $airline->id)->value('logo'))->toBe('airlines/1.webp')
        ->and(Asset::query()->count())->toBe(0);

    airlinePublicDisk()->assertExists('airlines/1.webp');
});
