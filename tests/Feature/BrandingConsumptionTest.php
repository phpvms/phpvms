<?php

declare(strict_types=1);

use App\Http\Middleware\UpdatePending;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
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
});

describe('admin panel, with branding configured', function (): void {
    beforeEach(function (): void {
        updateSetting('general.site_name', 'Acme Air');
        updateSetting('branding.logo_url', 'https://cdn.example.com/logo.png');
        updateSetting('branding.logo_32_url', 'https://cdn.example.com/logo-32.png');

        actingAs(actAsAdmin());
    });

    it('resolves the admin favicon and brand name through Branding', function (): void {
        Filament::setCurrentPanel('admin');
        $panel = Filament::getPanel('admin');

        expect($panel->getFavicon())->toBe('https://cdn.example.com/logo-32.png')
            ->and((string) $panel->getBrandName())->toBe('Acme Air');
    });

    it('shows the airline logo and name in the sidebar brand block and switcher', function (): void {
        get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Acme Air')
            ->assertSee('https://cdn.example.com/logo.png', escape: false);
    });
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
        get('/admin/dashboard')
            ->assertOk()
            ->assertSee(asset('assets/img/logo_blue.svg'), escape: false);
    });
});

it('leaves the system panel showing phpVMS branding regardless of airline settings', function (): void {
    updateSetting('general.site_name', 'Acme Air');
    updateSetting('branding.logo_url', 'https://cdn.example.com/logo.png');

    Filament::setCurrentPanel('system');

    expect(view('filament.shared.brand')->render())
        ->toContain('phpvms')
        ->toContain(asset('assets/img/logo_blue.svg'))
        ->not->toContain('Acme Air')
        ->not->toContain('https://cdn.example.com/logo.png');
});

describe('frontend (seven theme), with branding configured', function (): void {
    beforeEach(function (): void {
        actingAs(User::factory()->create());

        Theme::set('seven');
        updateSetting('general.theme', 'seven');
        updateSetting('general.site_name', 'Acme Air');
        updateSetting('branding.logo_url', 'https://cdn.example.com/logo.png');
        updateSetting('branding.banner_url', 'https://cdn.example.com/banner.png');
    });

    it('resolves favicon, logo and site name through Branding', function (): void {
        $this->followingRedirects()->get('/livemap')
            ->assertOk()
            ->assertSee('Acme Air')
            ->assertSee('https://cdn.example.com/logo.png', escape: false);
    });

    it('replaces the navbar logo with the uploaded one', function (): void {
        $this->followingRedirects()->get('/livemap')
            ->assertOk()
            ->assertSee('https://cdn.example.com/logo.png', escape: false)
            ->assertDontSee(public_asset('/assets/img/logo_blue_bg.svg'), escape: false);
    });

    it('emits an absolute og:image tag', function (): void {
        $this->followingRedirects()->get('/livemap')
            ->assertOk()
            ->assertSee('<meta property="og:image" content="https://cdn.example.com/banner.png" />', escape: false);
    });

    it('makes a relative banner path absolute, as Storage::url() returns on the local public disk', function (): void {
        updateSetting('branding.banner_url', '/storage/branding/banner.png');

        $this->followingRedirects()->get('/livemap')
            ->assertOk()
            ->assertSee('<meta property="og:image" content="'.url('/storage/branding/banner.png').'" />', escape: false);
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
