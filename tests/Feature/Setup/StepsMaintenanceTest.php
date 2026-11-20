<?php

declare(strict_types=1);

use App\Features\Setup\Steps\MaintenanceDown;
use App\Features\Setup\Steps\MaintenanceUp;
use App\Features\Setup\Steps\Preflight;
use App\Features\Setup\Support\StepContext;
use App\Filament\System\Installer;
use App\Filament\System\Updater;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;

function setupStepContext(): StepContext
{
    return new StepContext(false, static function (string $line): void {}, static function (int $done, int $total, ?string $label): void {});
}

function livewireUpdate(string ...$components): TestResponse
{
    return livewireUpdatePayload(['components' => array_map(static fn (string $component): array => [
        'snapshot' => json_encode([
            'data'     => [],
            'memo'     => ['id' => 'abc123', 'name' => $component],
            'checksum' => 'not-validated',
        ]),
        'updates' => [],
        'calls'   => [],
    ], $components)]);
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

test('while down, /system/upgrade and the Livewire script answer and /admin returns 503', function (): void {
    Artisan::call('down');

    expect($this->get('/system/upgrade')->getStatusCode())->not->toBe(503)
        ->and($this->get(EndpointResolver::scriptPath(minified: !config('app.debug')))->getStatusCode())->toBe(200)
        ->and($this->get('/admin')->getStatusCode())->toBe(503);
});

test('while down, the web cron route is not blocked', function (): void {
    Artisan::call('down');

    expect($this->get('/api/cron/not-the-secret')->getStatusCode())->not->toBe(503);
});

test('while down, a Livewire update for a non-setup component returns 503', function (): void {
    Artisan::call('down');

    expect(livewireUpdate('App\Filament\Pages\SomethingElse')->getStatusCode())->toBe(503)
        ->and(livewireUpdate(Updater::class)->getStatusCode())->not->toBe(503);
});

test('while down, a Livewire update for the installer is exempt', function (): void {
    Artisan::call('down');

    expect(livewireUpdate(Installer::class)->getStatusCode())->not->toBe(503);
});

test('while down, a Livewire update mixing a setup and a non-setup component returns 503', function (): void {
    Artisan::call('down');

    expect(livewireUpdate(Updater::class, 'App\Filament\Pages\SomethingElse')->getStatusCode())->toBe(503);
});

test('while down, a malformed Livewire update returns 503', function (): void {
    Artisan::call('down');

    expect(livewireUpdatePayload([])->getStatusCode())->toBe(503)
        ->and(livewireUpdatePayload(['components' => [['snapshot' => 'not json', 'updates' => [], 'calls' => []]]])->getStatusCode())->toBe(503);
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
