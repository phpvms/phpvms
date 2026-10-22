<?php

declare(strict_types=1);

use App\Features\Assets\AssetService;
use App\Models\Asset;
use App\Models\Rank;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * Rank's HasAssets cleanup, mirroring AwardAssetTest -- the trait's deleted()
 * hook is shared, so this is the same behavior on a second model rather than
 * a second implementation.
 */
function rankBadgeDisk(): FilesystemAdapter
{
    return Storage::disk(config('filesystems.public_files'));
}

function rankBadgeAsset(Rank $rank): ?Asset
{
    return app(AssetService::class)->find(Asset::SLOT_RANK, (string) $rank->id);
}

beforeEach(function (): void {
    fakeAssetDisks();
});

it('resolves image_url from the rank asset when one exists', function (): void {
    $rank = Rank::factory()->create(['image_url' => null]);
    $asset = app(AssetService::class)->storeContents(ASSET_TEST_PNG, Asset::SLOT_RANK, (string) $rank->id, storage: (string) config('filesystems.public_files'));

    expect($rank->refresh()->image_url)->toBe($asset->url());
});

it('deletes the badge asset and its file when the rank is force-deleted', function (): void {
    $rank = Rank::factory()->create(['image_url' => null]);
    $asset = app(AssetService::class)->storeContents(ASSET_TEST_PNG, Asset::SLOT_RANK, (string) $rank->id, storage: (string) config('filesystems.public_files'));
    rankBadgeDisk()->assertExists($asset->path);

    $rank->forceDelete();

    expect(Asset::query()->whereKey($asset->id)->exists())->toBeFalse();
    rankBadgeDisk()->assertMissing($asset->path);
});

it('keeps the badge asset and file when the rank is only soft-deleted, and it still resolves after restore', function (): void {
    $rank = Rank::factory()->create(['image_url' => null]);
    $asset = app(AssetService::class)->storeContents(ASSET_TEST_PNG, Asset::SLOT_RANK, (string) $rank->id, storage: (string) config('filesystems.public_files'));

    $rank->delete();

    expect(Asset::query()->whereKey($asset->id)->exists())->toBeTrue();
    rankBadgeDisk()->assertExists($asset->path);

    $rank->restore();

    expect($rank->image_url)->toBe($asset->fresh()->url())
        ->and(rankBadgeAsset($rank))->not->toBeNull();
});
