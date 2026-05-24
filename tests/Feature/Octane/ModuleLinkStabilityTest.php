<?php

declare(strict_types=1);

use App\Services\ModuleService;

/**
 * ModuleService used to hold its admin/frontend link arrays as `protected
 * static array`. Static state survives across requests on the same Octane
 * worker, which is fine when registration happens once at module boot but
 * was a footgun for any future per-request caller. The arrays are now
 * instance state on a shared singleton; this test asserts that fetching
 * the link list multiple times in a row does not grow it.
 *
 * @group octane
 */
pest()->group('octane');

test('admin link count is stable across repeated reads', function (): void {
    /** @var ModuleService $service */
    $service = app(ModuleService::class);

    $service->addAdminLink('Sample', '/admin/sample', 'pe-7s-note');

    $baseline = count($service->getAdminLinks());

    foreach (range(1, 5) as $_) {
        expect(count($service->getAdminLinks()))->toBe($baseline);
    }
});

test('frontend link count is stable across repeated reads', function (): void {
    /** @var ModuleService $service */
    $service = app(ModuleService::class);

    $service->addFrontendLink('Sample', '/sample', 'bi bi-people', true);

    $baseline = count($service->getFrontendLinks(true));

    foreach (range(1, 5) as $_) {
        expect(count($service->getFrontendLinks(true)))->toBe($baseline);
    }
});
