<?php

declare(strict_types=1);

use App\Services\SimBriefEmbedCapabilityService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::flush();
});

it('caches the framing probe for six hours and refreshes after expiry', function (): void {
    Http::fake([
        SimBriefEmbedCapabilityService::PROVIDER_URL => Http::sequence()
            ->push('', 200)
            ->push('', 200, ['X-Frame-Options' => 'DENY']),
    ]);

    $service = app(SimBriefEmbedCapabilityService::class);

    expect($service->allowed())->toBeTrue()
        ->and($service->allowed())->toBeTrue();
    Http::assertSentCount(1);

    $this->travel(6)->hours();
    $this->travel(1)->seconds();

    expect($service->allowed())->toBeFalse();
    Http::assertSentCount(2);
});

it('only skips the modal when the provider explicitly blocks framing', function (): void {
    Http::fake([
        SimBriefEmbedCapabilityService::PROVIDER_URL => Http::sequence()
            ->push('', 200, ['Content-Security-Policy' => "default-src 'self'; frame-ancestors 'self'"])
            ->pushStatus(503)
            ->pushFailedConnection(),
    ]);

    $service = app(SimBriefEmbedCapabilityService::class);

    expect($service->allowed())->toBeFalse();

    Cache::flush();

    expect($service->allowed())->toBeTrue();

    Cache::flush();

    expect($service->allowed())->toBeTrue();
});
