<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Models\NavigationItem;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Facades\Filament;

/**
 * 6.1: the `app` layout, the sections addon views fill, the meta tags, theme
 * mode before paint, the scripts, and persist/navigate on spa items only.
 */
beforeEach(function (): void {
    $this->pilot = useAirside();
    $this->withoutVite();
});

it('renders an addon view that extends app inside the shell, with its sections', function (): void {
    $html = $this->actingAs($this->pilot)->get('/airside-probe/pilot')->assertOk()->getContent();

    expect($html)
        ->toContain('<title>Addon page - ')
        ->toContain('id="addon-css"')
        ->toContain('name="addon-head"')
        ->toContain('x-data="tallstackui_layout()"')
        ->toContain('id="addon-body"');

    // The content sits inside the layout's main element.
    expect($html)->toMatch('/<main [^>]*>.*id="addon-body".*<\/main>/s');
});

it('includes the addon script exactly once, after the Livewire scripts', function (): void {
    $html = $this->actingAs($this->pilot)->get('/airside-probe/pilot')->getContent();

    expect(substr_count($html, 'id="addon-script"'))->toBe(1)
        ->and(strpos($html, 'id="addon-script"'))->toBeGreaterThan(strpos($html, '/livewire'));
});

it('renders the required meta tags', function (): void {
    $html = $this->actingAs($this->pilot)->get('/airside-probe/pilot')->getContent();

    expect($html)
        ->toContain('<meta name="base-url" content="'.url('').'">')
        ->toContain('<meta name="api-key" content="'.$this->pilot->api_key.'">')
        ->toMatch('/<meta name="csrf-token" content="[^"]+">/');
});

it('applies the saved theme mode before first paint', function (): void {
    $html = $this->actingAs($this->pilot)->get('/airside-probe/pilot')->getContent();

    // TallStackUI's theme switch keeps the mode under `dark-theme`; the head script reads the same key.
    $script = strpos($html, "localStorage.getItem('dark-theme')");

    expect($script)->not->toBeFalse()
        ->and($script)->toBeLessThan(strpos($html, '<body'))
        ->and($html)->toContain("classList.toggle('dark'")
        ->and($html)->toContain("document.addEventListener('livewire:navigated', apply)")
        ->and($html)->toContain('x-data="tallstackui_darkTheme(');
});

it('loads the Livewire and TallStackUI scripts', function (): void {
    $html = $this->actingAs($this->pilot)->get('/airside-probe/pilot')->getContent();

    expect($html)
        ->toContain('/livewire')
        ->toContain('/tallstackui/script/');
});

it('lets a guest see the layout with a sign in entry', function (): void {
    $html = $this->get('/airside-probe')->assertOk()->getContent();

    expect($html)
        ->toContain('id="addon-body"')
        ->toContain(url('/login'))
        ->not->toContain(url('/logout'));
});

it('offers profile and sign out to a signed-in pilot, with the three theme modes', function (): void {
    $html = $this->actingAs($this->pilot)->get('/airside-probe/pilot')->getContent();

    expect($html)
        ->toContain(url('/profile'))
        ->toContain(url('/logout'))
        ->toContain($this->pilot->name)
        ->toContain("setAs('light')")
        ->toContain("setAs('dark')")
        ->toContain("setAs('system')");
});

it('offers Go to Admin only to a user with admin access, as a full-load link', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $adminUrl = Filament::getPanel('admin')->getUrl();

    $pilotHtml = $this->actingAs($this->pilot)->get('/airside-probe/pilot')->getContent();
    expect($pilotHtml)->not->toContain(__('ui.go_to_admin'));

    // ShellData is request-scoped; a test process keeps the first request's instance.
    app()->forgetScopedInstances();

    $adminHtml = $this->actingAs(createAdminUser())->get('/airside-probe/pilot')->getContent();
    expect($adminHtml)->toContain(__('ui.go_to_admin'))
        ->toMatch('/<a\s+href="'.preg_quote($adminUrl, '/').'"(?![^>]*wire:navigate)[^>]*>/s');
});

it('navigates in-app menu items and leaves external and new-window items as plain links', function (): void {
    NavigationItem::query()->delete();
    NavigationItem::factory()->forRoute('frontend.dashboard.index')->create(['label' => 'Spa item']);
    NavigationItem::factory()->create(['label' => 'Plain url', 'target_value' => 'https://example.com/x']);
    NavigationItem::factory()->forRoute('frontend.flights.index')->create(['label' => 'Out', 'new_window' => true]);

    $html = $this->actingAs($this->pilot)->get('/airside-probe/pilot')->getContent();

    expect($html)->toMatch('/<a\s+href="[^"]*dashboard"[^>]*wire:navigate.hover/s')
        ->and($html)->not->toMatch('/<a\s+href="https:\/\/example.com\/x"[^>]*wire:navigate/s')
        ->and($html)->toMatch('/<a\s+href="[^"]*flights"[^>]*target="_blank"[^>]*rel="noopener noreferrer"/s')
        ->and($html)->not->toMatch('/<a\s+href="[^"]*flights"[^>]*wire:navigate/s');
});

it('persists nothing in the shell across navigation', function (): void {
    $html = $this->actingAs($this->pilot)->get('/airside-probe/pilot')->getContent();

    // The sidebar and header re-render per page: the current item, open groups,
    // sector, duty and station stay server-driven, and the header's mobile menu
    // button keeps driving the page's own sidebar.
    expect($html)->not->toContain('x-persist=');
});
