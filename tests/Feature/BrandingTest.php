<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Support\Branding;

function brandingMigrationForBrandingTest(): object
{
    return require base_path('database/migrations_data/2026_08_13_000000_branding_settings.php');
}

function setBrandingSetting(string $key, string $value): void
{
    Setting::where('id', Setting::formatKey($key))->update(['value' => $value]);
}

it('resolves via the container as a singleton', function (): void {
    expect(app(Branding::class))->toBeInstanceOf(Branding::class)
        ->and(app(Branding::class))->toBe(app(Branding::class));
});

it('declares no non-static properties holding resolved values', function (): void {
    $instanceProperties = array_filter(
        new ReflectionClass(Branding::class)->getProperties(),
        fn (ReflectionProperty $property): bool => !$property->isStatic(),
    );

    expect($instanceProperties)->toBeEmpty();
});

describe('with no settings rows at all', function (): void {
    beforeEach(function (): void {
        // TestCase seeds SettingsSeeder for every test; simulate a bare
        // install (or one that has not yet run migrate-data) by wiping it.
        Setting::query()->delete();
    });

    it('every accessor falls back without raising SettingNotFound', function (): void {
        $branding = app(Branding::class);

        expect($branding->name())->toBe(config('app.name'))
            ->and($branding->logo())->toBe(asset('assets/img/logo_blue.svg'))
            ->and($branding->logo(32))->toBe(asset('assets/img/logo_blue.svg'))
            ->and($branding->logo(64))->toBe(asset('assets/img/logo_blue.svg'))
            ->and($branding->logo(180))->toBe(asset('assets/img/logo_blue.svg'))
            ->and($branding->favicon())->toBe(asset('assets/img/favicon.png'))
            ->and($branding->banner())->toBeNull()
            ->and($branding->brandColor())->toBe('#067ec1');
    });
});

describe('with the branding rows seeded but empty', function (): void {
    beforeEach(function (): void {
        brandingMigrationForBrandingTest()->up();
    });

    it('falls back to the bundled assets and app config', function (): void {
        $branding = app(Branding::class);

        expect($branding->name())->toBe(config('app.name'))
            ->and($branding->logo())->toBe(asset('assets/img/logo_blue.svg'))
            ->and($branding->favicon())->toBe(asset('assets/img/favicon.png'))
            ->and($branding->banner())->toBeNull()
            ->and($branding->brandColor())->toBe('#067ec1');
    });

    it('returns the setting value once one is set', function (): void {
        setBrandingSetting('general.site_name', 'Acme Air');
        setBrandingSetting('branding.brand_color', '#ff0000');
        setBrandingSetting('branding.logo_url', 'https://cdn.example.com/logo.png');
        setBrandingSetting('branding.banner_url', 'https://cdn.example.com/banner.png');

        $branding = app(Branding::class);

        expect($branding->name())->toBe('Acme Air')
            ->and($branding->brandColor())->toBe('#ff0000')
            ->and($branding->logo())->toBe('https://cdn.example.com/logo.png')
            ->and($branding->banner())->toBe('https://cdn.example.com/banner.png');
    });

    it('sized logo falls back to the original when the derivative is empty', function (): void {
        setBrandingSetting('branding.logo_url', 'https://cdn.example.com/logo.png');

        expect(app(Branding::class)->logo(64))->toBe('https://cdn.example.com/logo.png');
    });

    it('sized logo prefers the derivative over the original', function (): void {
        setBrandingSetting('branding.logo_url', 'https://cdn.example.com/logo.png');
        setBrandingSetting('branding.logo_64_url', 'https://cdn.example.com/logo-64.png');

        expect(app(Branding::class)->logo(64))->toBe('https://cdn.example.com/logo-64.png');
    });

    it('favicon never falls back to the full-size original', function (): void {
        setBrandingSetting('branding.logo_url', 'https://cdn.example.com/logo.png');

        // logo_32_url is deliberately left empty.
        expect(app(Branding::class)->favicon())->toBe(asset('assets/img/favicon.png'))
            ->and(app(Branding::class)->favicon())->not->toBe('https://cdn.example.com/logo.png');
    });

    it('favicon uses the 32px derivative when present', function (): void {
        setBrandingSetting('branding.logo_url', 'https://cdn.example.com/logo.png');
        setBrandingSetting('branding.logo_32_url', 'https://cdn.example.com/logo-32.png');

        expect(app(Branding::class)->favicon())->toBe('https://cdn.example.com/logo-32.png');
    });
});
