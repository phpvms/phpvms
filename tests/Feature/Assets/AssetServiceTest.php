<?php

declare(strict_types=1);

use App\Features\Assets\AssetService;
use App\Features\Assets\Enums\AssetSlot;
use App\Features\Assets\Enums\AssetType;
use App\Features\Assets\Models\Asset;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** A real 1x1 PNG, so the mime sniffer has actual bytes to read. */
const PNG_BYTES = "\x89PNG\r\n\x1a\n\x00\x00\x00\x0dIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\x0aIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\x0d\x0a\x2d\xb4\x00\x00\x00\x00IEND\xaeB\x60\x82";

beforeEach(function (): void {
    fakeAssetDisks();
    $this->service = app(AssetService::class);
});

/**
 * A real upload over a real temp file, with a client-supplied mime type the
 * caller chooses.
 *
 * UploadedFile::fake() is useless here: Illuminate\Http\Testing\File overrides
 * getMimeType() to return MimeType::from($name) (Testing/File.php:132-134), so
 * a fake never sniffs and the client type and the sniffed type can never
 * disagree — which is the whole thing under test.
 */
function assetUpload(string $name, string $contents, string $clientMime): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'asset-test-');
    file_put_contents($path, $contents);

    // $test: true — the file did not arrive over HTTP, so the upload checks
    // would otherwise reject it.
    return new UploadedFile($path, $name, $clientMime, null, true);
}

/**
 * A file the vocabulary does not accept has no extension to store it under and
 * no Content-Type to replay it with, so there is nothing sensible to do but
 * refuse. The name here is a .png: if the service trusted the client-supplied
 * type this would sail through and land a text file as an image.
 */
it('rejects a content type no asset type accepts', function (): void {
    $file = assetUpload('logo.png', 'this is plain text, not an image', 'image/png');

    expect(fn () => $this->service->store($file, AssetSlot::BRANDING, 'logo'))
        ->toThrow(InvalidArgumentException::class);

    expect(Asset::query()->count())->toBe(0);
});

/**
 * The other direction of the same rule. getClientMimeType() is guessed from the
 * upload's name and is attacker-controlled; getMimeType() reads the bytes. The
 * stored content type, type and extension must all come from the bytes.
 */
it('sniffs the content type from the bytes rather than the client type', function (): void {
    $file = assetUpload('sneaky.txt', PNG_BYTES, 'text/plain');

    // Guard: the two disagree, so this test can actually fail.
    expect($file->getClientMimeType())->toBe('text/plain')
        ->and($file->getMimeType())->toBe('image/png');

    $asset = $this->service->store($file, AssetSlot::BRANDING, 'logo');

    expect($asset->content_type)->toBe('image/png')
        ->and($asset->type)->toBe(AssetType::IMAGE)
        ->and($asset->path)->toEndWith('.png')
        ->and($asset->filename())->toBe('logo.png');
});

/**
 * Replacement is by (slot, key): one row, new bytes, and the old file gone —
 * but gone only after the row points at the new one, so a failure mid-store
 * leaves the old bytes reachable instead of a row pointing at nothing.
 */
it('replaces an asset in place and drops the old file', function (): void {
    $first = $this->service->store(
        assetUpload('a.png', PNG_BYTES, 'image/png'),
        AssetSlot::BRANDING,
        'logo',
    );
    $oldPath = $first->path;

    $second = $this->service->store(
        assetUpload('b.png', PNG_BYTES."\x00padding", 'image/png'),
        AssetSlot::BRANDING,
        'logo',
    );

    expect($second->id)->toBe($first->id)
        ->and(Asset::query()->count())->toBe(1)
        ->and($second->path)->not->toBe($oldPath);

    Storage::disk($second->diskName())->assertMissing($oldPath);
    // The row's file is the one that survived, not the one that got deleted.
    Storage::disk($second->diskName())->assertExists($second->fresh()->path);
});

/**
 * The only route to an asset's bytes is its row, so a row deleted without its
 * file orphans bytes nothing can reach.
 */
it('deletes the file when the asset is deleted', function (): void {
    $asset = $this->service->store(
        assetUpload('a.png', PNG_BYTES, 'image/png'),
        AssetSlot::BRANDING,
        'logo',
    );

    Storage::disk($asset->diskName())->assertExists($asset->path);

    $asset->delete();

    Storage::disk($asset->diskName())->assertMissing($asset->path);
});

/**
 * adopt() records a file that is already on the disk, in place. For migrating
 * something into assets without changing the URL it is already served under —
 * a public asset's URL comes from its path, so copying to a fresh path would
 * break every URL an install has published.
 */
it('adopts an existing file without moving or copying it', function (): void {
    $disk = Storage::disk(config('filesystems.public_files'));
    $disk->put('branding/logo.png', PNG_BYTES);

    $asset = $this->service->adopt('branding/logo.png', AssetSlot::BRANDING, 'logo', isPublic: true);

    expect($asset->path)->toBe('branding/logo.png')
        ->and($asset->content_type)->toBe('image/png')
        ->and($asset->url())->toBe($disk->url('branding/logo.png'))
        ->and($asset->size)->toBe(strlen(PNG_BYTES))
        // Exactly one copy of the bytes.
        ->and($disk->allFiles())->toBe(['branding/logo.png']);
});

/**
 * Re-adopting the same path must not delete the file. The replace path deletes
 * whatever the old row pointed at, and here that is the same file the new row
 * points at — deleting it would destroy the asset it just recorded.
 */
it('survives adopting the same path twice', function (): void {
    $disk = Storage::disk(config('filesystems.public_files'));
    $disk->put('branding/logo.png', PNG_BYTES);

    $this->service->adopt('branding/logo.png', AssetSlot::BRANDING, 'logo', isPublic: true);
    $second = $this->service->adopt('branding/logo.png', AssetSlot::BRANDING, 'logo', isPublic: true);

    $disk->assertExists('branding/logo.png');
    expect(Asset::query()->count())->toBe(1)
        ->and($disk->get($second->path))->toBe(PNG_BYTES);
});

it('refuses to adopt a path with no file', function (): void {
    expect(fn () => $this->service->adopt('branding/missing.png', AssetSlot::BRANDING, 'logo', isPublic: true))
        ->toThrow(InvalidArgumentException::class);

    expect(Asset::query()->count())->toBe(0);
});

/**
 * Where the bytes land is decided by is_public, because that is what decides
 * how they are served: a public asset gets a storage URL off the public disk, a
 * private one has no URL at all and is reachable only through the API endpoint.
 */
it('stores public and private assets on different disks', function (): void {
    $private = $this->service->store(
        assetUpload('a.png', PNG_BYTES, 'image/png'),
        AssetSlot::SOUNDS,
        'private',
    );
    $public = $this->service->store(
        assetUpload('b.png', PNG_BYTES, 'image/png'),
        AssetSlot::BRANDING,
        'public',
        isPublic: true,
    );

    Storage::disk(Asset::PRIVATE_DISK)->assertExists($private->path);
    Storage::disk(Asset::PRIVATE_DISK)->assertMissing($public->path);

    Storage::disk(config('filesystems.public_files'))->assertExists($public->path);
    Storage::disk(config('filesystems.public_files'))->assertMissing($private->path);

    expect($private->url())->toContain('/api/v1/assets/'.$private->id)
        ->and($public->url())->not->toContain('/api/');
});

/**
 * A replacement can flip visibility, which moves the asset to the other disk.
 * Deleting the old file from the NEW disk would leave the original behind — and
 * on the public disk that means bytes still reachable by URL after the asset
 * was supposedly made private.
 */
it('removes the old file from its own disk when a replacement flips visibility', function (): void {
    $public = $this->service->store(
        assetUpload('a.png', PNG_BYTES, 'image/png'),
        AssetSlot::BRANDING,
        'logo',
        isPublic: true,
    );
    $publicPath = $public->path;

    $private = $this->service->store(
        assetUpload('b.png', PNG_BYTES."\x00v2", 'image/png'),
        AssetSlot::BRANDING,
        'logo',
        isPublic: false,
    );

    Storage::disk(config('filesystems.public_files'))->assertMissing($publicPath);
    Storage::disk(Asset::PRIVATE_DISK)->assertExists($private->path);
});

/**
 * (slot, key) is the identity a disk cache lays out as {slot}/{key}.{ext}, and
 * `source` is deliberately not part of it — two modules writing the same slot
 * and key would collide there. The DB has to hold that line even when a caller
 * bypasses store().
 */
it('enforces (slot, key) uniqueness across sources', function (): void {
    $this->service->store(
        assetUpload('a.png', PNG_BYTES, 'image/png'),
        AssetSlot::BRANDING,
        'logo',
    );

    expect(fn () => Asset::create([
        'key'          => 'logo',
        'slot'         => AssetSlot::BRANDING,
        'type'         => AssetType::IMAGE,
        'source'       => 'acars',
        'content_type' => 'image/png',
        'path'         => 'assets/branding/other.png',
        'last_update'  => 'x',
        'size'         => 1,
    ]))->toThrow(QueryException::class);
});

/**
 * Same key in a different slot is a different asset, and a second module
 * writing the same (slot, key) replaces rather than duplicates.
 */
it('scopes keys per slot and replaces across sources', function (): void {
    $branding = $this->service->store(
        assetUpload('a.png', PNG_BYTES, 'image/png'),
        AssetSlot::BRANDING,
        'logo',
    );
    $airline = $this->service->store(
        assetUpload('b.png', PNG_BYTES, 'image/png'),
        AssetSlot::AIRLINE_LOGO,
        'logo',
    );

    expect($airline->id)->not->toBe($branding->id)
        ->and(Asset::query()->count())->toBe(2);

    $replaced = $this->service->store(
        assetUpload('c.png', PNG_BYTES, 'image/png'),
        AssetSlot::BRANDING,
        'logo',
        source: 'acars',
    );

    expect($replaced->id)->toBe($branding->id)
        ->and($replaced->source)->toBe('acars')
        ->and(Asset::query()->count())->toBe(2);
});

/**
 * list() is the manifest every consumer reads, so its filters are load-bearing.
 */
it('lists by slot, type and source', function (): void {
    $this->service->store(assetUpload('a.png', PNG_BYTES, 'image/png'), AssetSlot::BRANDING, 'logo');
    $this->service->store(assetUpload('b.png', PNG_BYTES, 'image/png'), AssetSlot::GAUGE, 'dial', source: 'acars');

    expect($this->service->list(AssetSlot::BRANDING))->toHaveCount(1)
        ->and($this->service->list(null, AssetType::IMAGE))->toHaveCount(2)
        ->and($this->service->list(null, null, 'acars'))->toHaveCount(1)
        ->and($this->service->list(AssetSlot::GAUGE, AssetType::SOUND))->toHaveCount(0)
        ->and($this->service->find(AssetSlot::GAUGE, 'dial')?->key)->toBe('dial')
        ->and($this->service->find(AssetSlot::GAUGE, 'missing'))->toBeNull();
});
