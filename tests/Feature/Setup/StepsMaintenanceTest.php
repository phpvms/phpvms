<?php

declare(strict_types=1);

use App\Features\Setup\Steps\MaintenanceDown;
use App\Features\Setup\Steps\MaintenanceUp;
use App\Features\Setup\Steps\Preflight;
use App\Features\Setup\Support\MigrationPaths;
use App\Features\Setup\Support\StepContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;

function setupStepContext(): StepContext
{
    return new StepContext(false, static function (string $line): void {}, static function (int $done, int $total, ?string $label): void {});
}

/**
 * A Livewire update for components rendered on the given page paths.
 */
function livewireUpdate(string ...$paths): TestResponse
{
    return livewireUpdatePayload(['components' => array_map(static fn (string $path): array => [
        'snapshot' => json_encode([
            'data'     => [],
            'memo'     => ['id' => 'abc123', 'name' => 'any', 'path' => $path, 'method' => 'GET'],
            'checksum' => 'not-validated',
        ]),
        'updates' => [],
        'calls'   => [],
    ], $paths)]);
}

/**
 * @param array<string, mixed> $payload
 */
function livewireUpdatePayload(array $payload): TestResponse
{
    return test()->postJson(EndpointResolver::updatePath(), $payload, ['X-Livewire' => 'true']);
}

beforeEach(function (): void {
    config(['app.maintenance.driver' => 'file']);
});

afterEach(function (): void {
    Artisan::call('up');
});

test('preflight throws and names the missing extension', function (): void {
    config(['phpvms.installer.extensions' => ['phpvms_missing_ext']]);

    expect(fn () => new Preflight()->run(setupStepContext()))
        ->toThrow(RuntimeException::class, 'phpvms_missing_ext');
});

test('preflight throws when the maintenance driver is not file', function (): void {
    config(['app.maintenance.driver' => 'cache']);

    expect(fn () => new Preflight()->run(setupStepContext()))
        ->toThrow(RuntimeException::class, 'file');
});

test('a test taking the site down leaves the real maintenance file alone', function (): void {
    $real = base_path('storage/framework/down');
    $wasDown = is_file($real);

    Artisan::call('down');

    expect(app()->isDownForMaintenance())->toBeTrue()
        ->and(is_file($real))->toBe($wasDown);
});

test('while down, /system/upgrade, /admin pages and the Livewire script answer and public pages return 503', function (): void {
    Artisan::call('down');

    expect($this->get('/system/upgrade')->getStatusCode())->not->toBe(503)
        ->and($this->get('/admin/login')->getStatusCode())->not->toBe(503)
        ->and($this->get(EndpointResolver::scriptPath(minified: !config('app.debug')))->getStatusCode())->toBe(200)
        ->and($this->get('/')->getStatusCode())->toBe(503)
        ->and($this->get('/flights')->getStatusCode())->toBe(503);
});

test('while down, Livewire updates from the admin and setup pages are exempt, whatever the component', function (): void {
    Artisan::call('down');

    // The progress poll arrives batched with the toast and topbar, all rendered on the same page.
    expect(livewireUpdate('system/upgrade', 'system/upgrade', 'system/upgrade')->getStatusCode())->not->toBe(503)
        ->and(livewireUpdate('admin/install')->getStatusCode())->not->toBe(503)
        ->and(livewireUpdate('admin/system/update')->getStatusCode())->not->toBe(503)
        ->and(livewireUpdate('admin', 'admin/pireps')->getStatusCode())->not->toBe(503);
});

test('while down, the upgrade page can send back every component it rendered, in one batch', function (): void {
    app()->instance(MigrationPaths::class, new class() extends MigrationPaths
    {
        public function pendingSchema(): array
        {
            return ['2026_01_01_000000_pending'];
        }

        public function pendingData(): array
        {
            return [];
        }
    });
    Artisan::call('down');

    $html = $this->get('/system/upgrade')->assertOk()->getContent();
    preg_match_all('/wire:snapshot="([^"]+)"/', (string) $html, $matches);
    $components = array_map(static fn (string $snapshot): array => [
        'snapshot' => html_entity_decode($snapshot, ENT_QUOTES),
        'updates'  => [],
        'calls'    => [],
    ], $matches[1]);

    expect(count($components))->toBeGreaterThan(1)
        ->and(livewireUpdatePayload(['components' => $components])->getStatusCode())->not->toBe(503);
});

test('while down, a Livewire update from a public page returns 503', function (): void {
    Artisan::call('down');

    expect(livewireUpdate('flights')->getStatusCode())->toBe(503)
        ->and(livewireUpdate('')->getStatusCode())->toBe(503)
        ->and(livewireUpdate('system/upgrade', 'flights')->getStatusCode())->toBe(503);
});

test('while down, a malformed Livewire update returns 503', function (): void {
    Artisan::call('down');

    expect(livewireUpdatePayload([])->getStatusCode())->toBe(503)
        ->and(livewireUpdatePayload(['components' => [['snapshot' => 'not json', 'updates' => [], 'calls' => []]]])->getStatusCode())->toBe(503)
        ->and(livewireUpdatePayload(['components' => [['snapshot' => json_encode(['memo' => ['name' => 'any']]), 'updates' => [], 'calls' => []]]])->getStatusCode())->toBe(503);
});

test('maintenance_down is a no-op when already down', function (): void {
    $ctx = setupStepContext();

    new MaintenanceDown()->run($ctx);
    new MaintenanceDown()->run($ctx);
    expect(app()->isDownForMaintenance())->toBeTrue();

    new MaintenanceUp()->run($ctx);
    new MaintenanceUp()->run($ctx);
    expect(app()->isDownForMaintenance())->toBeFalse();
});
