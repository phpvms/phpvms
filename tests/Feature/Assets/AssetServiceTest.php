<?php

declare(strict_types=1);

use App\Features\Assets\AssetService;
use App\Features\Assets\AssetTypes;
use App\Models\Asset;
use Illuminate\Contracts\Filesystem\Filesystem;
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

    expect(fn () => $this->service->store($file, Asset::SLOT_BRANDING, 'logo'))
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

    $asset = $this->service->store($file, Asset::SLOT_BRANDING, 'logo');

    expect($asset->content_type)->toBe('image/png')
        ->and($asset->type)->toBe(AssetTypes::IMAGE)
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
        Asset::SLOT_BRANDING,
        'logo',
    );
    $oldPath = $first->path;

    $second = $this->service->store(
        assetUpload('b.png', PNG_BYTES."\x00padding", 'image/png'),
        Asset::SLOT_BRANDING,
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
        Asset::SLOT_BRANDING,
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

    $asset = $this->service->adopt('branding/logo.png', Asset::SLOT_BRANDING, 'logo', storage: (string) config('filesystems.public_files'));

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

    $this->service->adopt('branding/logo.png', Asset::SLOT_BRANDING, 'logo', storage: (string) config('filesystems.public_files'));
    $second = $this->service->adopt('branding/logo.png', Asset::SLOT_BRANDING, 'logo', storage: (string) config('filesystems.public_files'));

    $disk->assertExists('branding/logo.png');
    expect(Asset::query()->count())->toBe(1)
        ->and($disk->get($second->path))->toBe(PNG_BYTES);
});

it('refuses to adopt a path with no file', function (): void {
    expect(fn () => $this->service->adopt('branding/missing.png', Asset::SLOT_BRANDING, 'logo', storage: (string) config('filesystems.public_files')))
        ->toThrow(InvalidArgumentException::class);

    expect(Asset::query()->count())->toBe(0);
});

/**
 * Slots are an open vocabulary — a module declares its own — so this format
 * check is what replaced the closed enum, not nothing. A slot becomes a
 * directory name and a URL segment downstream, so a separator or a traversal in
 * one is a path escape.
 */
it('rejects a slot that could escape its directory', function (): void {
    foreach (['../evil', 'foo/bar', 'foo\\bar', 'Foo', '', ' ', 'foo bar', '-foo'] as $slot) {
        expect(fn () => $this->service->storeContents(PNG_BYTES, $slot, 'logo'))
            ->toThrow(InvalidArgumentException::class);
    }

    expect(Asset::query()->count())->toBe(0);
});

it('accepts a slot a module declares for itself', function (): void {
    // Nothing in core knows what a paintkit is; that is the point of the slot
    // being a string rather than an enum core ships.
    $asset = $this->service->storeContents(PNG_BYTES, 'paintkit', 'b738');

    expect($asset->slot)->toBe('paintkit')
        ->and($asset->path)->toStartWith(Asset::PATH_PREFIX.'/paintkit/');
});

/**
 * Kinds are a registry, so a module can add one core never shipped. Bytes
 * nothing has registered are still refused: an unregistered kind has no
 * extension to store under and no consumer that knows what to do with it.
 *
 * GIF stands in for a module's kind here because the content type has to be one
 * the sniffer can actually recognise — see the text-format caveat below.
 */
it('accepts a content type a module registered and refuses one nobody did', function (): void {
    $gif = "GIF89a\x01\x00\x01\x00\x00\xff\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x00;";

    expect(fn () => $this->service->storeContents($gif, 'gauge', 'spinner'))
        ->toThrow(InvalidArgumentException::class);

    app(AssetTypes::class)->register('animation', ['image/gif' => 'gif']);

    $asset = $this->service->storeContents($gif, 'gauge', 'spinner');

    expect($asset->type)->toBe('animation')
        ->and($asset->content_type)->toBe('image/gif')
        ->and($asset->path)->toEndWith('.gif');
});

/**
 * The limit that forced the declared-kind route: content sniffing CANNOT
 * identify a text format. CSS always reads as text/plain, and JS only sometimes
 * reads as application/javascript — `export const a = 1;` does, but
 * `console.log("hi");` reads as text/plain too.
 *
 * So "sniff, never trust the uploader" — right for images and audio, where the
 * magic bytes are unambiguous — cannot be the whole rule for a stylesheet.
 * Sniffing alone still accepts nothing, which is what the caller has to work
 * around by naming the kind.
 */
it('still cannot sniff a text format', function (): void {
    app(AssetTypes::class)->register('css', ['text/css' => 'css']);

    expect(fn () => $this->service->storeContents('body { color: red }', 'gauge', 'theme'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported asset content type [text/plain]');
});

/**
 * The way through: the caller names the kind. The stored content type comes
 * from the registry, not from the caller — it only picked which registered kind
 * applies, so it can never inject a Content-Type of its choosing.
 */
it('stores a text format when the caller declares the kind', function (): void {
    app(AssetTypes::class)->register('css', ['text/css' => 'css']);

    $asset = $this->service->storeContents('body { color: red }', 'gauge', 'theme', type: 'css');

    expect($asset->type)->toBe('css')
        ->and($asset->content_type)->toBe('text/css')
        ->and($asset->path)->toEndWith('.css');
});

/**
 * Declaring a kind does not switch sniffing off, it demotes it to a veto. Bytes
 * that sniff to a kind we recognise are authoritative, so an image cannot be
 * filed as a stylesheet and later replayed as one.
 */
it('refuses bytes that contradict the declared kind', function (): void {
    app(AssetTypes::class)->register('css', ['text/css' => 'css']);

    expect(fn () => $this->service->storeContents(PNG_BYTES, 'gauge', 'theme', type: 'css'))
        ->toThrow(InvalidArgumentException::class, 'declared as [css] but its bytes are [image/png]');
});

/**
 * The veto's other half. When the sniff DOES recognise the bytes and agrees
 * with the declaration, it is more specific than the kind's canonical entry and
 * must win. `image` is canonically image/png, so falling back to it filed SVG
 * bytes as image/png under a .png — the wrong Content-Type on delivery, and
 * invisible to GenerateBrandingSizes' SVG branch, which keys on the extension.
 */
it('keeps the sniffed content type when it agrees with the declared kind', function (): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>';

    $asset = $this->service->storeContents($svg, Asset::SLOT_BRANDING, 'logo', type: 'image');

    expect($asset->type)->toBe('image')
        ->and($asset->content_type)->toBe('image/svg+xml')
        ->and($asset->path)->toEndWith('.svg');
});

/**
 * Same rule for a kind a module registers. `sound` is canonically audio/mpeg
 * (the first entry the ACARS module registers), so WAV bytes declared `sound`
 * used to be stored as audio/mpeg under a .mp3 and replayed with that header.
 */
it('keeps the sniffed content type for a module kind too', function (): void {
    app(AssetTypes::class)->register('sound', [
        'audio/mpeg'  => 'mp3',
        'audio/x-wav' => 'wav',
    ]);

    // A minimal RIFF/WAVE header — enough for finfo to name it.
    $wav = 'RIFF'.pack('V', 36)."WAVEfmt \x10\x00\x00\x00\x01\x00\x01\x00\x44\xac\x00\x00\x88\x58\x01\x00\x02\x00\x10\x00".'data'.pack('V', 0);

    $asset = $this->service->storeContents($wav, 'sounds', 'ding', type: 'sound');

    expect($asset->type)->toBe('sound')
        ->and($asset->content_type)->toBe('audio/x-wav')
        ->and($asset->path)->toEndWith('.wav');
});

it('refuses a declared kind nothing registered', function (): void {
    expect(fn () => $this->service->storeContents('body { color: red }', 'gauge', 'theme', type: 'nonsense'))
        ->toThrow(InvalidArgumentException::class, 'No asset type [nonsense] is registered.');
});

/**
 * The declared kind selects among registered kinds; it never becomes the
 * content type itself. A caller passing a MIME string where a kind belongs is
 * rejected rather than quietly stored with a caller-chosen Content-Type.
 */
it('will not take a content type where a kind belongs', function (): void {
    app(AssetTypes::class)->register('css', ['text/css' => 'css']);

    expect(fn () => $this->service->storeContents('body { color: red }', 'gauge', 'theme', type: 'text/css'))
        ->toThrow(InvalidArgumentException::class, 'No asset type [text/css] is registered.');
});

/**
 * `local` and `public` are configured `'throw' => false`
 * (config/filesystems.php:58,67), so a failed write returns false instead of
 * raising. Persisting the row regardless would leave an asset pointing at a
 * file that is not there — a 404 for every consumer, and no clue why.
 */
it('does not persist a row when the bytes cannot be written', function (): void {
    $failing = Mockery::mock(Filesystem::class);
    $failing->shouldReceive('put')->once()->andReturn(false);

    Storage::shouldReceive('disk')->with(Asset::STORAGE_LOCAL)->andReturn($failing);

    expect(fn () => $this->service->storeContents(PNG_BYTES, Asset::SLOT_BRANDING, 'logo'))
        ->toThrow(RuntimeException::class, 'Could not write asset bytes');

    expect(Asset::query()->count())->toBe(0);
});

/**
 * The caller names the disk and nothing substitutes a different one. What that
 * choice decides downstream is whether the asset has a URL of its own: the
 * public disk declares one, `local` does not, and an asset with none is
 * reachable only through the API endpoint.
 */
it('stores each asset on the disk the caller named', function (): void {
    $private = $this->service->store(
        assetUpload('a.png', PNG_BYTES, 'image/png'),
        'sounds',
        'private',
    );
    $public = $this->service->store(
        assetUpload('b.png', PNG_BYTES, 'image/png'),
        Asset::SLOT_BRANDING,
        'public',
        storage: (string) config('filesystems.public_files'),
    );

    Storage::disk(Asset::STORAGE_LOCAL)->assertExists($private->path);
    Storage::disk(Asset::STORAGE_LOCAL)->assertMissing($public->path);

    Storage::disk(config('filesystems.public_files'))->assertExists($public->path);
    Storage::disk(config('filesystems.public_files'))->assertMissing($private->path);

    expect($private->storage)->toBe(Asset::STORAGE_LOCAL)
        ->and($private->url())->toBeNull()
        ->and($public->storage)->toBe(config('filesystems.public_files'))
        ->and($public->url())->toBeString();
});

/**
 * A replacement can name a different disk, which moves the asset. Deleting the
 * old file from the NEW disk would leave the original behind — and on a disk
 * with a URL that means bytes still reachable after the asset was supposedly
 * moved out of reach.
 */
it('removes the old file from its own disk when a replacement changes disk', function (): void {
    $public = $this->service->store(
        assetUpload('a.png', PNG_BYTES, 'image/png'),
        Asset::SLOT_BRANDING,
        'logo',
        storage: (string) config('filesystems.public_files'),
    );
    $publicPath = $public->path;

    $private = $this->service->store(
        assetUpload('b.png', PNG_BYTES."\x00v2", 'image/png'),
        Asset::SLOT_BRANDING,
        'logo',
        storage: Asset::STORAGE_LOCAL,
    );

    Storage::disk(config('filesystems.public_files'))->assertMissing($publicPath);
    Storage::disk(Asset::STORAGE_LOCAL)->assertExists($private->path);
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
        Asset::SLOT_BRANDING,
        'logo',
    );

    expect(fn () => Asset::create([
        'key'          => 'logo',
        'slot'         => Asset::SLOT_BRANDING,
        'type'         => AssetTypes::IMAGE,
        'source'       => 'acars',
        'content_type' => 'image/png',
        'path'         => 'assets/branding/other.png',
        'storage'      => Asset::STORAGE_LOCAL,
        'last_update'  => 'x',
        'size'         => 1,
    ]))->toThrow(QueryException::class);
});

/**
 * `url` names the link sentinel in the same column real disk names live in, so
 * bytes can never be written under it — a row claiming its `path` is a URL when
 * it is a path would resolve to a URL that is really a filename. This is also
 * what an install configuring a disk under that literal name runs into, which is
 * the point: refuse rather than silently reinterpret.
 */
it('refuses to write bytes to the link sentinel', function (): void {
    $disks = config('filesystems.disks');

    try {
        // Guard: the rejection is the sentinel's, not merely "no such disk" —
        // configure one under that name and it is still refused.
        config(['filesystems.disks.url' => ['driver' => 'local', 'root' => sys_get_temp_dir()]]);

        expect(fn () => $this->service->storeContents(PNG_BYTES, Asset::SLOT_BRANDING, 'logo', storage: Asset::STORAGE_URL))
            ->toThrow(InvalidArgumentException::class, 'reserved for external links');

        expect(Asset::query()->count())->toBe(0);
    } finally {
        // A disk really named `url` must not survive into another test — one
        // asserts that resolving it throws.
        config(['filesystems.disks' => $disks]);
    }
});

it('refuses a disk nothing configured', function (): void {
    expect(fn () => $this->service->storeContents(PNG_BYTES, Asset::SLOT_BRANDING, 'logo', storage: 'nowhere'))
        ->toThrow(InvalidArgumentException::class, 'No filesystem disk [nowhere] is configured.');

    expect(fn () => $this->service->adopt('branding/logo.png', Asset::SLOT_BRANDING, 'logo', storage: 'nowhere'))
        ->toThrow(InvalidArgumentException::class, 'No filesystem disk [nowhere] is configured.');
});

/**
 * adopt() keeps a file's existing path, so re-adopting the SAME path onto a
 * different disk is the one case where the old file is not at the new location.
 * Comparing paths alone would call that unchanged and orphan the original.
 */
it('removes the old file when only the disk changed under an adopted path', function (): void {
    $public = config('filesystems.public_files');

    Storage::disk($public)->put('branding/logo.png', PNG_BYTES);
    Storage::disk(Asset::STORAGE_LOCAL)->put('branding/logo.png', PNG_BYTES);

    $this->service->adopt('branding/logo.png', Asset::SLOT_BRANDING, 'logo', storage: $public);
    $moved = $this->service->adopt('branding/logo.png', Asset::SLOT_BRANDING, 'logo', storage: Asset::STORAGE_LOCAL);

    expect($moved->storage)->toBe(Asset::STORAGE_LOCAL);

    Storage::disk(Asset::STORAGE_LOCAL)->assertExists('branding/logo.png');
    Storage::disk($public)->assertMissing('branding/logo.png');
});

/**
 * Same key in a different slot is a different asset, and a second module
 * writing the same (slot, key) replaces rather than duplicates.
 */
it('scopes keys per slot and replaces across sources', function (): void {
    $branding = $this->service->store(
        assetUpload('a.png', PNG_BYTES, 'image/png'),
        Asset::SLOT_BRANDING,
        'logo',
    );
    $airline = $this->service->store(
        assetUpload('b.png', PNG_BYTES, 'image/png'),
        Asset::SLOT_AIRLINE_LOGO,
        'logo',
    );

    expect($airline->id)->not->toBe($branding->id)
        ->and(Asset::query()->count())->toBe(2);

    $replaced = $this->service->store(
        assetUpload('c.png', PNG_BYTES, 'image/png'),
        Asset::SLOT_BRANDING,
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
    $this->service->store(assetUpload('a.png', PNG_BYTES, 'image/png'), Asset::SLOT_BRANDING, 'logo');
    $this->service->store(assetUpload('b.png', PNG_BYTES, 'image/png'), 'gauge', 'dial', source: 'acars');

    expect($this->service->list(Asset::SLOT_BRANDING))->toHaveCount(1)
        ->and($this->service->list(null, AssetTypes::IMAGE))->toHaveCount(2)
        ->and($this->service->list(null, null, 'acars'))->toHaveCount(1)
        ->and($this->service->list('gauge', 'sound'))->toHaveCount(0)
        ->and($this->service->find('gauge', 'dial')?->key)->toBe('dial')
        ->and($this->service->find('gauge', 'missing'))->toBeNull();
});
