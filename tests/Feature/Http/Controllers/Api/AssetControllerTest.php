<?php

declare(strict_types=1);

use App\Features\Assets\AssetService;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    fakeAssetDisks();
});

function storeAsset(string $key, string $storage = Asset::STORAGE_LOCAL): Asset
{
    return app(AssetService::class)->storeContents(
        ASSET_TEST_PNG."\x00".$key,
        $storage === Asset::STORAGE_LOCAL ? 'sounds' : Asset::SLOT_BRANDING,
        $key,
        storage: $storage,
    );
}

/** The route to an asset's bytes. It is addressed by id, never by path. */
function assetRoute(Asset $asset): string
{
    return route('api.assets.show', $asset);
}

/**
 * The endpoint exists for assets with no URL of their own. A token has to carry
 * `assets:read` — an unrelated read scope must not open it.
 */
it('serves an asset to a token holding assets:read', function (): void {
    $asset = storeAsset('gear-warning');

    apiAsToken(User::factory()->create(), ['assets:read']);

    $response = $this->get(assetRoute($asset));

    $response->assertOk()->assertHeader('Content-Type', 'image/png');

    expect($asset->url())->toBeNull()
        ->and($response->streamedContent())->toBe(ASSET_TEST_PNG."\x00gear-warning");
});

it('refuses an anonymous request', function (): void {
    $asset = storeAsset('gear-warning');

    $this->getJson(assetRoute($asset))->assertUnauthorized();
});

it('refuses a token without assets:read', function (): void {
    $asset = storeAsset('gear-warning');

    apiAsToken(User::factory()->create(), ['airlines:read']);

    $this->getJson(assetRoute($asset))->assertForbidden();
});

/**
 * Which disk an asset sits on is not this route's business. An asset with a URL
 * of its own is simply also fetchable here, by a caller that has the scope —
 * the row does not carry a second, weaker copy of that decision.
 */
it('serves an asset from a disk that has its own URL too', function (): void {
    $asset = storeAsset('logo', (string) config('filesystems.public_files'));

    expect($asset->url())->toBeString();

    apiAsToken(User::factory()->create(), ['assets:read']);

    $this->get(assetRoute($asset))->assertOk();
});

/**
 * A link owns no bytes and its `path` is a URL, so there is nothing to stream —
 * and no disk called `url` to ask, which is what a 500 here would look like.
 */
it('404s for a link asset', function (): void {
    $asset = Asset::create([
        'key'         => 'cdn-logo',
        'slot'        => Asset::SLOT_BRANDING,
        'type'        => 'image',
        'path'        => 'https://cdn.example.com/logo.png',
        'storage'     => Asset::STORAGE_URL,
        'last_update' => 'x',
        'size'        => 0,
    ]);

    apiAsToken(User::factory()->create(), ['assets:read']);

    $this->getJson(assetRoute($asset))->assertNotFound();
});

/**
 * The URL is keyed on the asset id and survives a replacement, so a cached copy
 * must be revalidated rather than trusted. `last_update` is a hash of the
 * bytes, which is what makes revalidation cheap.
 */
it('sends a revalidating ETag and answers a matching one with a 304', function (): void {
    $asset = storeAsset('gear-warning');

    apiAsToken(User::factory()->create(), ['assets:read']);

    $first = $this->get(assetRoute($asset));
    $first->assertOk();

    $etag = $first->headers->get('ETag');

    expect($etag)->toBe('"'.$asset->last_update.'"')
        ->and($first->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('must-revalidate')
        // An id-addressed URL whose content can change must never be immutable.
        ->not->toContain('immutable');

    $this->withHeaders(['If-None-Match' => $etag])
        ->get(assetRoute($asset))
        ->assertStatus(304);
});

/**
 * Replacing the bytes changes the stamp, so a client holding the old ETag is
 * told to refetch even though the URL never moved. This is the case a long
 * max-age or an `immutable` would break.
 */
it('stops matching the old ETag once the bytes change', function (): void {
    $asset = storeAsset('gear-warning');
    $stale = '"'.$asset->last_update.'"';

    app(AssetService::class)->storeContents(
        ASSET_TEST_PNG."\x00different",
        'sounds',
        'gear-warning',
    );

    apiAsToken(User::factory()->create(), ['assets:read']);

    $this->withHeaders(['If-None-Match' => $stale])
        ->get(assetRoute($asset))
        ->assertOk();
});

/** A row whose file has gone is not a 500. */
it('404s when the row survives but the file does not', function (): void {
    $asset = storeAsset('gear-warning');

    Storage::disk($asset->diskName())->delete($asset->path);

    apiAsToken(User::factory()->create(), ['assets:read']);

    $this->getJson(assetRoute($asset))->assertNotFound();
});
