<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Support\Branding;
use Filament\Support\Colors\Color;

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
            ->and($branding->brandColor())->toBe('#067ec1')
            ->and($branding->hasLogo())->toBeFalse();
    });
});

describe('with the branding rows seeded but empty', function (): void {
    beforeEach(function (): void {
        brandingMigrationForBrandingTest()->up();
        fakeAssetDisks();
    });

    it('falls back to the bundled assets and app config', function (): void {
        $branding = app(Branding::class);

        expect($branding->name())->toBe(config('app.name'))
            ->and($branding->logo())->toBe(asset('assets/img/logo_blue.svg'))
            ->and($branding->favicon())->toBe(asset('assets/img/favicon.png'))
            ->and($branding->banner())->toBeNull()
            ->and($branding->brandColor())->toBe('#067ec1');
    });

    it('returns the stored value once one is set', function (): void {
        setBrandingSetting('general.site_name', 'Acme Air');
        setBrandingSetting('branding.brand_color', '#ff0000');
        $logo = createBrandingAsset(Branding::KEY_LOGO);
        $banner = createBrandingAsset(Branding::KEY_BANNER);

        $branding = app(Branding::class);

        expect($branding->name())->toBe('Acme Air')
            ->and($branding->brandColor())->toBe('#ff0000')
            ->and($branding->logo())->toBe($logo->url())
            ->and($branding->banner())->toBe($banner->url());
    });

    it('hasLogo is false until a logo has been uploaded', function (): void {
        expect(app(Branding::class)->hasLogo())->toBeFalse();
    });

    it('hasLogo is true once a logo has been uploaded', function (): void {
        createBrandingAsset(Branding::KEY_LOGO);

        expect(app(Branding::class)->hasLogo())->toBeTrue();
    });

    it('sized logo falls back to the original when the derivative is missing', function (): void {
        $logo = createBrandingAsset(Branding::KEY_LOGO);

        expect(app(Branding::class)->logo(64))->toBe($logo->url());
    });

    it('sized logo prefers the derivative over the original', function (): void {
        $logo = createBrandingAsset(Branding::KEY_LOGO);
        $derivative = createBrandingAsset(Branding::KEY_LOGO.'-64');

        expect(app(Branding::class)->logo(64))->toBe($derivative->url())
            ->and(app(Branding::class)->logo(64))->not->toBe($logo->url());
    });

    it('favicon never falls back to the full-size original', function (): void {
        $logo = createBrandingAsset(Branding::KEY_LOGO);

        // The logo-32 derivative is deliberately absent.
        expect(app(Branding::class)->favicon())->toBe(asset('assets/img/favicon.png'))
            ->and(app(Branding::class)->favicon())->not->toBe($logo->url());
    });

    it('favicon uses the 32px derivative when present', function (): void {
        createBrandingAsset(Branding::KEY_LOGO);
        $favicon = createBrandingAsset(Branding::KEY_LOGO.'-32');

        expect(app(Branding::class)->favicon())->toBe($favicon->url());
    });

    it('logoDark falls back to the light logo when unset', function (): void {
        $logo = createBrandingAsset(Branding::KEY_LOGO);

        expect(app(Branding::class)->logoDark())->toBe($logo->url());
    });

    it('logoDark returns the dark logo once one is set', function (): void {
        $logo = createBrandingAsset(Branding::KEY_LOGO);
        $dark = createBrandingAsset(Branding::KEY_LOGO_DARK);

        expect(app(Branding::class)->logoDark())->toBe($dark->url())
            ->and(app(Branding::class)->logoDark())->not->toBe($logo->url());
    });

    it('hasDarkLogo is false until a dark logo has been uploaded', function (): void {
        expect(app(Branding::class)->hasDarkLogo())->toBeFalse();
    });

    it('hasDarkLogo is true once a dark logo has been uploaded', function (): void {
        createBrandingAsset(Branding::KEY_LOGO_DARK);

        expect(app(Branding::class)->hasDarkLogo())->toBeTrue();
    });

    it('brandPalette resolves the exact Filament palette when the setting is a known palette name, case-insensitively', function (): void {
        setBrandingSetting('branding.brand_color', 'Blue');

        expect(app(Branding::class)->brandPalette())->toBe(Color::Blue);
    });

    it('brandPalette generates a palette from a stored hex', function (): void {
        setBrandingSetting('branding.brand_color', '#4f46e5');

        expect(app(Branding::class)->brandPalette())->toBe(Color::generatePalette('#4f46e5'));
    });

    it('brandPalette falls back to the default palette for a value that is neither a palette name nor a hex', function (): void {
        setBrandingSetting('branding.brand_color', 'not-a-color-or-palette');

        expect(app(Branding::class)->brandPalette())->toBe(Color::generatePalette('#067ec1'));
    });
});
