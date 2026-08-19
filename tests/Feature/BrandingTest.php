<?php

declare(strict_types=1);

use App\Jobs\GenerateBrandingSizes;
use App\Models\Setting;
use App\Support\Branding;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Queue;

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
        $derivative = createBrandingAsset(Branding::logoKey(32));

        expect(app(Branding::class)->favicon())->toBe($derivative->url());
    });

    it('favicon falls through to a larger derivative when the smallest is missing', function (): void {
        createBrandingAsset(Branding::KEY_LOGO);
        $larger = createBrandingAsset(Branding::logoKey(180));

        expect(app(Branding::class)->favicon())->toBe($larger->url());
    });

    it('favicon takes the smallest derivative when several exist', function (): void {
        $smallest = createBrandingAsset(Branding::logoKey(32));
        createBrandingAsset(Branding::logoKey(64));
        createBrandingAsset(Branding::logoKey(180));

        expect(app(Branding::class)->favicon())->toBe($smallest->url());
    });

    it('favicon prefers an uploaded favicon over the logo derivative', function (): void {
        createBrandingAsset(Branding::KEY_LOGO);
        $derivative = createBrandingAsset(Branding::logoKey(32));
        $favicon = createBrandingAsset(Branding::KEY_FAVICON);

        expect(app(Branding::class)->favicon())->toBe($favicon->url())
            ->and(app(Branding::class)->favicon())->not->toBe($derivative->url());
    });

    it('favicon uses an uploaded favicon when no logo exists at all', function (): void {
        $favicon = createBrandingAsset(Branding::KEY_FAVICON);

        expect(app(Branding::class)->favicon())->toBe($favicon->url());
    });

    it('store dispatches the size job for the logo but not for its derivatives', function (): void {
        Queue::fake();

        $branding = app(Branding::class);
        $branding->store(Branding::KEY_LOGO, ASSET_TEST_PNG);

        Queue::assertPushed(GenerateBrandingSizes::class, 1);

        // The job writes the derivatives back through store(); dispatching
        // again for one of those keys would be an infinite loop.
        $branding->store(Branding::logoKey(32), ASSET_TEST_PNG);

        Queue::assertPushed(GenerateBrandingSizes::class, 1);
    });

    it('forget removes the asset and is a no-op when nothing is stored', function (): void {
        $branding = app(Branding::class);
        createBrandingAsset(Branding::KEY_BANNER);

        $branding->forget(Branding::KEY_BANNER);
        expect($branding->banner())->toBeNull();

        $branding->forget(Branding::KEY_BANNER);
        expect($branding->banner())->toBeNull();
    });

    it('hasFavicon distinguishes an uploaded favicon from a derived one', function (): void {
        createBrandingAsset(Branding::logoKey(32));

        expect(app(Branding::class)->hasFavicon())->toBeFalse();

        createBrandingAsset(Branding::KEY_FAVICON);

        expect(app(Branding::class)->hasFavicon())->toBeTrue();
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
