<?php

declare(strict_types=1);

use App\Features\Assets\AssetService;
use App\Http\Data\AssetData;
use App\Models\Asset;

beforeEach(function (): void {
    fakeAssetDisks();
});

/**
 * `url` is non-nullable on the wire, but `Asset::url()` is not: an asset on a
 * disk that declares no URL has no address of its own. Core's authenticated
 * endpoint is exactly what that case exists for, so it is the default rather
 * than a TypeError.
 */
it('falls back to the core endpoint for an asset with no URL of its own', function (): void {
    $asset = app(AssetService::class)->storeContents(ASSET_TEST_PNG, Asset::SLOT_BRANDING, 'logo');

    expect($asset->url())->toBeNull()
        ->and(AssetData::fromModel($asset)->url)->toBe(route('api.assets.show', $asset));
});

/** A disk that declares a URL wins: no reason to add a hop and an auth check. */
it('uses the asset own URL when its disk declares one', function (): void {
    $asset = app(AssetService::class)->storeContents(
        ASSET_TEST_PNG,
        Asset::SLOT_BRANDING,
        'logo',
        storage: (string) config('filesystems.public_files'),
    );

    expect(AssetData::fromModel($asset)->url)->toBe($asset->url());
});

/** An explicit override beats both — the caller knows its own audience. */
it('lets the caller name a different door', function (): void {
    $asset = app(AssetService::class)->storeContents(ASSET_TEST_PNG, Asset::SLOT_BRANDING, 'logo');

    expect(AssetData::fromModel($asset, 'https://example.com/bytes')->url)
        ->toBe('https://example.com/bytes');
});
