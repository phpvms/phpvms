<?php

declare(strict_types=1);

use App\Enums\PirepState;
use App\Filament\Actions\TestMapStyleAction;
use App\Filament\Pages\LiveFlights;
use App\Filament\Pages\Maintenance;
use App\Filament\Resources\Pireps\Pages\ViewPirep;
use App\Models\Permission;
use App\Models\Pirep;
use App\Models\PirepPosition;
use App\Models\User;
use App\Services\Finance\PirepFinanceService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

/**
 * Admin users who only hold a page's `view:` permission must not be able to run
 * its mutating actions, even with a crafted Livewire request.
 */
beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

function viewOnlyAdmin(string ...$permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        $user->givePermissionTo($name);
    }

    return $user->fresh();
}

it('blocks pirep finance recalculation without the edit permission', function (): void {
    $pirep = Pirep::factory()->create(['state' => PirepState::ACCEPTED]);
    $this->actingAs(viewOnlyAdmin('view:pirep'));

    $this->mock(PirepFinanceService::class)->shouldNotReceive('processFinancesForPirep');

    livewire(ViewPirep::class, ['record' => $pirep->id])
        ->call('recalculateFinances')
        ->assertForbidden();
});

it('blocks maintenance actions without the edit permission', function (): void {
    setting_save('cron.random_id', 'keep-me');
    $this->actingAs(viewOnlyAdmin('view:maintenance'));

    $component = livewire(Maintenance::class);
    foreach (['enableWebCron', 'disableWebCron', 'checkForPhpVMSUpdates', 'clearCache', 'flushFailedJobs', 'resyncAllSeeds', 'optimizeApp', 'archivePirepFlights'] as $action) {
        $component->assertActionHidden($action);
    }

    $component
        ->call('mountAction', 'disableWebCron')
        ->call('callMountedAction');

    expect(setting('cron.random_id'))->toBe('keep-me');
});

it('runs maintenance actions with the edit permission', function (): void {
    setting_save('cron.random_id', 'keep-me');
    $this->actingAs(viewOnlyAdmin('view:maintenance', 'edit:maintenance'));

    livewire(Maintenance::class)->callAction('disableWebCron');

    expect(setting('cron.random_id'))->toBe('');
});

it('hides the live flight purge without the edit permission', function (): void {
    $this->actingAs(viewOnlyAdmin('view:live-flights'));

    $pirep = Pirep::factory()->create(['state' => PirepState::IN_PROGRESS]);
    PirepPosition::factory()->create(['pirep_id' => $pirep->id]);

    livewire(LiveFlights::class)
        ->assertActionHidden(TestAction::make('purge')->table($pirep));
});

it('refuses to fetch a map style from a non-public address', function (string $url): void {
    Http::fake();

    expect(TestMapStyleAction::isPublicHttpUrl($url))->toBeFalse();
})->with([
    'loopback'      => 'http://127.0.0.1:8080/style.json',
    'private'       => 'http://10.0.0.5/style.json',
    'link-local'    => 'http://169.254.169.254/latest/meta-data/',
    'ipv6 loopback' => 'http://[::1]/style.json',
    'localhost'     => 'http://localhost/style.json',
    'file scheme'   => 'file:///etc/passwd',
    'shared/cgnat'  => 'http://100.100.100.200/latest/meta-data/',
]);

it('does not follow a redirect to a non-public address', function (): void {
    Http::fake([
        '8.8.8.8/*'   => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin']),
        '127.0.0.1/*' => Http::response(['version' => 8, 'sources' => [], 'layers' => []]),
    ]);

    expect(TestMapStyleAction::fetch('https://8.8.8.8/style.json'))->toBeNull();
    Http::assertSentCount(1);
});

it('follows a redirect to another public address', function (): void {
    Http::fake([
        '8.8.8.8/*' => Http::response('', 302, ['Location' => 'https://1.1.1.1/style.json']),
        '1.1.1.1/*' => Http::response(['version' => 8, 'sources' => [], 'layers' => []]),
    ]);

    expect(TestMapStyleAction::fetch('https://8.8.8.8/style.json')?->json('version'))->toBe(8);
});

it('allows a map style from a public address', function (): void {
    expect(TestMapStyleAction::isPublicHttpUrl('https://8.8.8.8/style.json'))->toBeTrue();
});
