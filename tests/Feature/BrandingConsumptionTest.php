<?php

declare(strict_types=1);

use App\Http\Middleware\UpdatePending;
use App\Models\Role;
use App\Models\User;
use App\Support\Branding;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Igaster\LaravelTheme\Facades\Theme;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Group 4 ("consume branding") replaced six hardcoded phpVMS render sites
 * with reads through App\Support\Branding. These tests cover the two
 * guarantees the requirements care about: an install with branding
 * configured shows the airline's values, and an install with nothing
 * configured renders byte-identical to before this change.
 */
function actAsAdmin(): User
{
    $role = Role::create(['name' => Role::superAdminName(), 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

beforeEach(function (): void {
    $this->withoutMiddleware(UpdatePending::class);
    fakeAssetDisks();
});

describe('admin panel, with branding configured', function (): void {
    beforeEach(function (): void {
        updateSetting('general.site_name', 'Acme Air');
        $this->logo = createBrandingAsset(Branding::KEY_LOGO);
        $this->favicon = createBrandingAsset(Branding::KEY_LOGO.'-32');

        actingAs(actAsAdmin());
    });

    it('resolves the admin favicon and brand name through Branding', function (): void {
        Filament::setCurrentPanel('admin');
        $panel = Filament::getPanel('admin');

        expect($panel->getFavicon())->toBe($this->favicon->url())
            ->and((string) $panel->getBrandName())->toBe('Acme Air');
    });

    it('shows the airline logo and name in the sidebar brand block and switcher', function (): void {
        get('/admin')
            ->assertOk()
            ->assertSee('Acme Air')
            ->assertSee($this->logo->url(), escape: false);
    });
});

it("resolves the admin panel's primary colour to a stored palette name", function (): void {
    updateSetting('branding.brand_color', 'blue');

    Filament::setCurrentPanel('admin');

    expect(Filament::getPanel('admin')->getColors()['primary'])->toBe(Color::Blue);
});

describe('admin panel, with nothing configured', function (): void {
    beforeEach(function (): void {
        actingAs(actAsAdmin());
    });

    it('falls back to the pre-change favicon and brand name', function (): void {
        Filament::setCurrentPanel('admin');
        $panel = Filament::getPanel('admin');

        expect($panel->getFavicon())->toBe(asset('assets/img/favicon.png'))
            ->and((string) $panel->getBrandName())->toBe((string) config('app.name'));
    });

    it('renders the bundled logo in the sidebar brand block', function (): void {
        get('/admin')
            ->assertOk()
            ->assertSee(asset('assets/img/logo_blue.svg'), escape: false);
    });
});

it('renders a single img tag when no dark logo is set', function (): void {
    createBrandingAsset(Branding::KEY_LOGO);

    Filament::setCurrentPanel('admin');

    expect(substr_count(view('filament.shared.brand')->render(), '<img'))->toBe(1);
});

it('renders two img tags with dark-mode variant classes when a dark logo is set', function (): void {
    $logo = createBrandingAsset(Branding::KEY_LOGO);
    $dark = createBrandingAsset(Branding::KEY_LOGO_DARK);

    Filament::setCurrentPanel('admin');

    $html = view('filament.shared.brand')->render();

    expect(substr_count($html, '<img'))->toBe(2)
        ->and($html)->toContain($logo->url())
        ->and($html)->toContain($dark->url())
        ->and($html)->toContain('dark:hidden')
        ->and($html)->toContain('dark:block');
});

it('leaves the system panel showing phpVMS branding regardless of airline settings', function (): void {
    updateSetting('general.site_name', 'Acme Air');
    $logo = createBrandingAsset(Branding::KEY_LOGO);

    Filament::setCurrentPanel('system');

    expect(view('filament.shared.brand')->render())
        ->toContain('phpvms')
        ->toContain(asset('assets/img/logo_blue.svg'))
        ->not->toContain('Acme Air')
        ->not->toContain($logo->url());
});

describe('frontend (seven theme), with branding configured', function (): void {
    beforeEach(function (): void {
        actingAs(User::factory()->create());

        Theme::set('seven');
        updateSetting('general.theme', 'seven');
        updateSetting('general.site_name', 'Acme Air');
        $this->logo = createBrandingAsset(Branding::KEY_LOGO);
        $this->banner = createBrandingAsset(Branding::KEY_BANNER);
    });

    it('resolves favicon, logo and site name through Branding', function (): void {
        $this->followingRedirects()->get('/livemap')
            ->assertOk()
            ->assertSee('Acme Air')
            ->assertSee($this->logo->url(), escape: false);
    });

    it('replaces the navbar logo with the uploaded one', function (): void {
        $this->followingRedirects()->get('/livemap')
            ->assertOk()
            ->assertSee($this->logo->url(), escape: false)
            ->assertDontSee(public_asset('/assets/img/logo_blue_bg.svg'), escape: false);
    });

    // Still load-bearing after the move to assets: a public asset's URL comes
    // from Storage::url(), which on the local public disk is a relative
    // `/storage/...` path. og:image has to be absolute, so the theme's url()
    // wrapping is what makes this correct.
    it('emits an absolute og:image tag', function (): void {
        expect($this->banner->url())->toStartWith('/storage/');

        $this->followingRedirects()->get('/livemap')
            ->assertOk()
            ->assertSee('<meta property="og:image" content="'.url($this->banner->url()).'" />', escape: false);
    });
});

describe('frontend (seven theme), with nothing configured', function (): void {
    beforeEach(function (): void {
        actingAs(User::factory()->create());

        Theme::set('seven');
        updateSetting('general.theme', 'seven');
    });

    it('renders the pre-change favicon and site name', function (): void {
        $this->followingRedirects()->get('/livemap')
            ->assertOk()
            ->assertSee(asset('assets/img/favicon.png'), escape: false)
            ->assertSee((string) config('app.name'));
    });

    it('keeps the pre-change navbar logo -- the bundled favicon default is a different asset than this navbar used', function (): void {
        $this->followingRedirects()->get('/livemap')
            ->assertOk()
            ->assertSee(public_asset('/assets/img/logo_blue_bg.svg'), escape: false);
    });

    it('emits no og:image tag when no banner is configured', function (): void {
        $this->followingRedirects()->get('/livemap')
            ->assertOk()
            ->assertDontSee('og:image', escape: false);
    });
});
