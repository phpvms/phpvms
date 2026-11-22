<?php

declare(strict_types=1);

use App\Features\Addons\Services\AddonDiscoveryService;
use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Support\SetupRunState;
use App\Providers\AddonServiceProvider;
use Illuminate\Foundation\Application;

// Providers boot before the test body, so boot() is re-run with the app's
// cached console flag forced off to stand in for a web request.
function bootAddonProviderAsWebRequest(): void
{
    $app = app();
    $console = new ReflectionProperty(Application::class, 'isRunningInConsole');
    $console->setValue($app, false);

    try {
        new AddonServiceProvider($app)->boot();
    } finally {
        $console->setValue($app, true);
    }
}

it('boot does not prime addon discovery while a setup run is requested', function (): void {
    app(SetupRunState::class)->request('web', false);
    $this->mock(AddonDiscoveryService::class)->shouldReceive('primeIfNeeded')->never();

    bootAddonProviderAsWebRequest();
});

it('boot does not prime addon discovery after a setup run failed', function (): void {
    $state = app(SetupRunState::class);
    $state->request('web', false);
    $state->finished(RunStatus::Failed);
    $this->mock(AddonDiscoveryService::class)->shouldReceive('primeIfNeeded')->never();

    bootAddonProviderAsWebRequest();
});

it('boot primes addon discovery when no run is active', function (): void {
    $this->mock(AddonDiscoveryService::class)->shouldReceive('primeIfNeeded')->once();

    bootAddonProviderAsWebRequest();
});
