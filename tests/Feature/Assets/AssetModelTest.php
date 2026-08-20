<?php

declare(strict_types=1);

use App\Features\Assets\AssetTypes;
use App\Models\Asset;
use Illuminate\Support\Facades\Storage;

/**
 * Rows built directly rather than through AssetService: what is under test here
 * is what the model does with a `storage` value, and the service picks that
 * value for you.
 */
function assetRow(string $storage, string $path, string $key = 'logo'): Asset
{
    return Asset::create([
        'key'          => $key,
        'slot'         => Asset::SLOT_BRANDING,
        'type'         => AssetTypes::IMAGE,
        'content_type' => 'image/png',
        'path'         => $path,
        'storage'      => $storage,
        'last_update'  => 'x',
        'size'         => 1,
    ]);
}

/**
 * The disk declares a URL, so the asset is linkable directly and nothing needs
 * to route the bytes through PHP.
 */
it('resolves a URL from a disk that declares one', function (): void {
    $asset = assetRow('public', 'assets/branding/logo.png');

    expect($asset->url())->toBe(Storage::disk('public')->url('assets/branding/logo.png'))
        ->and($asset->url())->toStartWith('http');
});

/**
 * `local` has no `url` key at all (config/filesystems.php:54-60). Null means
 * "stream these yourself", not an error.
 */
it('resolves null for a disk with no configured URL', function (): void {
    expect(assetRow('local', 'assets/branding/logo.png')->url())->toBeNull();
});

/**
 * The reason the check is filled() and not isset(). The s3-family disks always
 * declare `url`, from an env var an operator may never have set — and Storage
 * will happily build a bare, wrong `/assets/...` out of an empty one.
 */
it('resolves null for a disk whose URL entry is empty', function (): void {
    foreach (['', null] as $configured) {
        config(['filesystems.disks.r2.url' => $configured]);

        // Guard: the key is present, so isset() would have passed here.
        expect(config()->has('filesystems.disks.r2.url'))->toBeTrue()
            ->and(assetRow('r2', 'assets/branding/logo.png', 'logo-'.var_export($configured, true))->url())
            ->toBeNull();
    }
});

it('returns the stored URL unchanged for a link asset', function (): void {
    $url = 'https://cdn.example.com/logo.png?v=2';

    expect(assetRow(Asset::STORAGE_URL, $url)->url())->toBe($url);
});

/**
 * A link asset owns no bytes and its `path` is not a path, so a delete that
 * reached for the filesystem would be asking a disk named `url` — which does
 * not exist — for a file named after a URL.
 */
it('touches no disk when a link asset is deleted', function (): void {
    // Guard: there is no disk called `url`, so any attempt to resolve one
    // throws — which is what makes this assertion able to fail.
    expect(fn () => Storage::disk(Asset::STORAGE_URL))->toThrow(InvalidArgumentException::class);

    assetRow(Asset::STORAGE_URL, 'https://cdn.example.com/logo.png')->delete();

    expect(Asset::query()->count())->toBe(0);
});

it('still deletes the file when a stored asset is deleted', function (): void {
    $disk = Storage::fake('public');
    $disk->put('assets/branding/logo.png', 'bytes');

    assetRow('public', 'assets/branding/logo.png')->delete();

    $disk->assertMissing('assets/branding/logo.png');
});
