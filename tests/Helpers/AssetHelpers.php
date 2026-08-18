<?php

use App\Features\Assets\AssetService;
use App\Features\Assets\Enums\AssetSlot;
use App\Features\Assets\Models\Asset;
use Illuminate\Support\Facades\Storage;

/**
 * A real 1x1 PNG. Assets sniff their content type from the bytes, so a test
 * fixture has to be a genuine image rather than a placeholder string.
 */
const ASSET_TEST_PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\x0dIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\x0aIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\x0d\x0a\x2d\xb4\x00\x00\x00\x00IEND\xaeB\x60\x82";

/**
 * Fake both disks an asset can live on. Public assets land on the public disk
 * and private ones on the private disk, so faking only one lets a test write
 * into real storage without noticing.
 *
 * Call this once per test, in beforeEach — Storage::fake() clears the disk, so
 * a second call mid-test destroys the files of everything created before it.
 */
function fakeAssetDisks(): void
{
    Storage::fake(Asset::PRIVATE_DISK);
    Storage::fake(config('filesystems.public_files'));
}

/**
 * Store a branding asset under $key. Public, as real branding is.
 *
 * Callers must have called {@see fakeAssetDisks()} first.
 *
 * Distinct bytes per key, so a test asserting that one accessor picked the
 * right asset cannot pass by accident when two keys collapse to one row.
 */
function createBrandingAsset(string $key, ?string $contents = null): Asset
{
    return app(AssetService::class)->storeContents(
        $contents ?? ASSET_TEST_PNG."\x00".$key,
        AssetSlot::BRANDING,
        $key,
        isPublic: true,
    );
}
