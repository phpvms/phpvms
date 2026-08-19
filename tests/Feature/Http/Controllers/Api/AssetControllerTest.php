<?php

declare(strict_types=1);

use App\Features\Assets\AssetService;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    fakeAssetDisks();
});

function storeAsset(string $key, bool $isPublic): Asset
{
    return app(AssetService::class)->storeContents(
        ASSET_TEST_PNG."\x00".$key,
        $isPublic ? Asset::SLOT_BRANDING : 'sounds',
        $key,
        isPublic: $isPublic,
    );
}

/**
 * The endpoint exists for assets with no URL of their own. A token has to carry
 * `assets:read` — an unrelated read scope must not open it.
 */
it('serves a private asset to a token holding assets:read', function (): void {
    $asset = storeAsset('gear-warning', isPublic: false);

    apiAsToken(User::factory()->create(), ['assets:read']);

    $response = $this->get($asset->url());

    $response->assertOk()->assertHeader('Content-Type', 'image/png');

    expect($response->streamedContent())->toBe(ASSET_TEST_PNG."\x00gear-warning");
});

it('refuses an anonymous request', function (): void {
    $asset = storeAsset('gear-warning', isPublic: false);

    $this->getJson($asset->url())->assertUnauthorized();
});

it('refuses a token without assets:read', function (): void {
    $asset = storeAsset('gear-warning', isPublic: false);

    apiAsToken(User::factory()->create(), ['airlines:read']);

    $this->getJson($asset->url())->assertForbidden();
});

/**
 * A public asset is served off the public disk by its storage URL. Serving it
 * here as well would give the same bytes two addresses and two cache lifetimes.
 */
it('404s for a public asset, which has a storage URL instead', function (): void {
    $asset = storeAsset('logo', isPublic: true);

    expect($asset->url())->not->toContain('/api/');

    apiAsToken(User::factory()->create(), ['assets:read']);

    $this->getJson(route('api.assets.show', $asset))->assertNotFound();
});

/**
 * The URL is keyed on the asset id and survives a replacement, so a cached copy
 * must be revalidated rather than trusted. `last_update` is a hash of the
 * bytes, which is what makes revalidation cheap.
 */
it('sends a revalidating ETag and answers a matching one with a 304', function (): void {
    $asset = storeAsset('gear-warning', isPublic: false);

    apiAsToken(User::factory()->create(), ['assets:read']);

    $first = $this->get($asset->url());
    $first->assertOk();

    $etag = $first->headers->get('ETag');

    expect($etag)->toBe('"'.$asset->last_update.'"')
        ->and($first->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('must-revalidate')
        // An id-addressed URL whose content can change must never be immutable.
        ->not->toContain('immutable');

    $this->withHeaders(['If-None-Match' => $etag])
        ->get($asset->url())
        ->assertStatus(304);
});

/**
 * Replacing the bytes changes the stamp, so a client holding the old ETag is
 * told to refetch even though the URL never moved. This is the case a long
 * max-age or an `immutable` would break.
 */
it('stops matching the old ETag once the bytes change', function (): void {
    $asset = storeAsset('gear-warning', isPublic: false);
    $stale = '"'.$asset->last_update.'"';

    app(AssetService::class)->storeContents(
        ASSET_TEST_PNG."\x00different",
        'sounds',
        'gear-warning',
    );

    apiAsToken(User::factory()->create(), ['assets:read']);

    $this->withHeaders(['If-None-Match' => $stale])
        ->get($asset->url())
        ->assertOk();
});

/** A row whose file has gone is not a 500. */
it('404s when the row survives but the file does not', function (): void {
    $asset = storeAsset('gear-warning', isPublic: false);

    Storage::disk($asset->diskName())->delete($asset->path);

    apiAsToken(User::factory()->create(), ['assets:read']);

    $this->getJson($asset->url())->assertNotFound();
});
