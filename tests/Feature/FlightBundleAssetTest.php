<?php

declare(strict_types=1);

use App\Features\Assets\AssetService;
use App\Models\Asset;
use App\Models\FlightBundle;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * FlightBundle's HasAssets wiring: it did not use the trait before this
 * change (image_url read Asset::getUrl() directly), so this also proves
 * assetSlot()/imageUrl() still resolve the hero image once routed through
 * HasAssets::assetUrl(), on top of the same cleanup Award and Rank get.
 */
function bundlePublicDisk(): FilesystemAdapter
{
    return Storage::disk(config('filesystems.public_files'));
}

function bundleAsset(FlightBundle $bundle): ?Asset
{
    return app(AssetService::class)->find(Asset::SLOT_BUNDLE, (string) $bundle->id);
}

beforeEach(function (): void {
    fakeAssetDisks();
});

it('resolves image_url from the bundle asset', function (): void {
    $bundle = FlightBundle::factory()->create();
    $asset = app(AssetService::class)->storeContents(ASSET_TEST_PNG, Asset::SLOT_BUNDLE, (string) $bundle->id, storage: (string) config('filesystems.public_files'));

    expect($bundle->refresh()->image_url)->toBe($asset->url());
});

it('is null with no hero image asset', function (): void {
    $bundle = FlightBundle::factory()->create();

    expect(bundleAsset($bundle))->toBeNull()
        ->and($bundle->image_url)->toBeNull();
});

it('deletes the hero image asset and its file when the bundle is force-deleted', function (): void {
    $bundle = FlightBundle::factory()->create();
    $asset = app(AssetService::class)->storeContents(ASSET_TEST_PNG, Asset::SLOT_BUNDLE, (string) $bundle->id, storage: (string) config('filesystems.public_files'));
    bundlePublicDisk()->assertExists($asset->path);

    $bundle->forceDelete();

    expect(Asset::query()->whereKey($asset->id)->exists())->toBeFalse();
    bundlePublicDisk()->assertMissing($asset->path);
});

it('keeps the hero image asset and file when the bundle is only soft-deleted, and it still resolves after restore', function (): void {
    $bundle = FlightBundle::factory()->create();
    $asset = app(AssetService::class)->storeContents(ASSET_TEST_PNG, Asset::SLOT_BUNDLE, (string) $bundle->id, storage: (string) config('filesystems.public_files'));

    $bundle->delete();

    expect(Asset::query()->whereKey($asset->id)->exists())->toBeTrue();
    bundlePublicDisk()->assertExists($asset->path);

    $bundle->restore();

    expect($bundle->image_url)->toBe($asset->fresh()->url())
        ->and(bundleAsset($bundle))->not->toBeNull();
});
