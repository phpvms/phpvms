<?php

declare(strict_types=1);

use App\Filament\Pages\SetupWizard;
use App\Http\Middleware\AdminGate;
use App\Http\Middleware\InstalledCheck;
use App\Models\Page;
use App\Models\User;
use App\Providers\Filament\BasePanelProvider;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Panel;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleComponents\Checksum;

beforeEach(function (): void {
    // TestCase turns these off suite-wide; this file covers them.
    $this->withMiddleware([AdminGate::class, InstalledCheck::class]);
    $this->seed(RolesPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
});

it('sends admin pages to the wizard until setup is complete', function (): void {
    updateSetting('general.setup_completed', false);
    $this->actingAs(createAdminUser(['airline_id' => null]));

    $this->get('/admin')->assertRedirect(SetupWizard::getUrl(panel: 'admin'));

    updateSetting('general.setup_completed', true);

    $this->get('/admin')->assertOk();
});

it('keeps the wizard and logout reachable while setup is incomplete', function (): void {
    updateSetting('general.setup_completed', false);
    $this->actingAs(createAdminUser(['airline_id' => null]));

    $this->get(SetupWizard::getUrl(panel: 'admin'))->assertOk();

    $this->post(Filament::getPanel('admin')->getLogoutUrl())
        ->assertRedirect()
        ->assertRedirectContains('login');
});

it('leaves non-admin users to the panel access check', function (): void {
    updateSetting('general.setup_completed', false);
    $this->actingAs(User::factory()->create());

    $this->get('/admin')->assertForbidden();
});

it('answers public pages with the set-up page until setup is complete', function (): void {
    User::factory()->create();
    updateSetting('general.setup_completed', false);

    $this->get('/')->assertStatus(503);

    updateSetting('general.setup_completed', true);

    expect($this->get('/')->status())->not->toBe(503);
});

it('gates a Livewire update from a page that was already open', function (): void {
    $this->actingAs(createAdminUser(['airline_id' => null]));

    $snapshot = static function ($response): array {
        preg_match('/wire:snapshot="([^"]+)"/', (string) $response->getContent(), $match);

        return ['snapshot' => htmlspecialchars_decode($match[1], ENT_QUOTES), 'updates' => [], 'calls' => []];
    };

    // Opened while setup is complete, then setup is reset underneath it.
    $page = $snapshot($this->get('/admin')->assertOk());
    $payload = ['components' => [$page]];
    $headers = ['X-Livewire' => 'true'];

    $update = Livewire::getUpdateUri();

    $this->postJson($update, $payload, $headers)->assertOk();

    updateSetting('general.setup_completed', false);
    // The package keeps per-request state that a real request starts without.
    Livewire::flushState();

    $this->postJson($update, $payload, $headers)->assertRedirect(SetupWizard::getUrl(panel: 'admin'));
});

/** @return array{snapshot: string, updates: array<mixed>, calls: array<mixed>} */
function pageComponent(string $html): array
{
    preg_match('/wire:snapshot="([^"]+)"/', $html, $match);

    return ['snapshot' => htmlspecialchars_decode($match[1], ENT_QUOTES), 'updates' => [], 'calls' => []];
}

it('ignores a forged components parameter on a public page', function (): void {
    User::factory()->create();
    $this->actingAs(createAdminUser(['airline_id' => null]));
    updateSetting('general.setup_completed', false);

    $wizard = pageComponent($this->get(SetupWizard::getUrl(panel: 'admin'))->assertOk()->getContent());

    $this->get('/?'.http_build_query(['components' => [$wizard]]))->assertStatus(503);
});

it('lets a Livewire batch of only exempt components through and gates one that mixes in another', function (): void {
    $this->actingAs(createAdminUser(['airline_id' => null]));

    $dashboard = pageComponent($this->get('/admin')->assertOk()->getContent());
    updateSetting('general.setup_completed', false);
    $wizard = pageComponent($this->get(SetupWizard::getUrl(panel: 'admin'))->assertOk()->getContent());

    $headers = ['X-Livewire' => 'true'];
    $update = Livewire::getUpdateUri();

    Livewire::flushState();
    $this->postJson($update, ['components' => [$dashboard, $wizard]], $headers)->assertRedirect(SetupWizard::getUrl(panel: 'admin'));

    Livewire::flushState();
    $this->postJson($update, ['components' => [$wizard]], $headers)->assertOk();
});

it('answers a Livewire update from a public page with 503 while setup is incomplete', function (): void {
    User::factory()->create();
    $this->actingAs(createAdminUser(['airline_id' => null]));

    // A signed snapshot that says it came from the public home page.
    $page = pageComponent($this->get('/admin')->assertOk()->getContent());
    $snapshot = json_decode($page['snapshot'], true);
    unset($snapshot['checksum']);
    $snapshot['memo']['path'] = '/';
    $snapshot['memo']['method'] = 'GET';
    $snapshot['checksum'] = Checksum::generate($snapshot);
    $payload = ['components' => [['snapshot' => json_encode($snapshot), 'updates' => [], 'calls' => []]]];

    updateSetting('general.setup_completed', false);
    Livewire::flushState();

    $this->postJson(Livewire::getUpdateUri(), $payload, ['X-Livewire' => 'true'])->assertStatus(503);
});

it('answers the plain web routes under admin/ with 503 while setup is incomplete', function (): void {
    // Auth sorts ahead of the gate, so only a signed-in admin reaches it.
    $this->actingAs(createAdminUser(['airline_id' => null]));
    updateSetting('general.setup_completed', false);

    $this->postJson('/admin/route-forge/api/lint')->assertStatus(503);
    $this->get('/admin/pages/'.Page::factory()->create()->id.'/preview')->assertStatus(503);
});

it('does not treat a public path that merely starts with admin as an admin page', function (): void {
    User::factory()->create();
    updateSetting('general.setup_completed', false);

    $this->get('/administration')->assertStatus(503);
});

it('gates every console panel, not only the admin one', function (): void {
    $provider = new class(app()) extends BasePanelProvider
    {
        public function panel(Panel $panel): Panel
        {
            return $this->applyConsoleChrome($panel);
        }
    };

    // A module panel cannot be routed in tests without a module on disk, so this checks the shared chrome they all use.
    expect($provider->panel(Panel::make())->getAuthMiddleware())
        ->toContain(Authenticate::class, AdminGate::class);
});
