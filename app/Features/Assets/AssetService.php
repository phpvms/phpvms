<?php

declare(strict_types=1);

namespace App\Features\Assets;

use App\Models\Asset;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Stores and lists assets. The single place that decides an asset's content
 * type, its stored path and its change stamp — none of which a caller supplies,
 * because all three are load-bearing downstream and a module getting any of
 * them wrong fails silently at the consumer.
 */
class AssetService
{
    /**
     * Store an uploaded file as an asset, replacing any existing asset with the
     * same (slot, key).
     *
     * `$storage` names the disk the bytes land on, and defaults to the local
     * disk, which has no URL of its own. A caller that wants its asset linkable
     * passes a disk that declares one — `config('filesystems.public_files')`
     * for site branding, which renders on the login screen. That decision is
     * the caller's: this service does not read config to guess it.
     *
     * The uploader chooses slot, key and the bytes. The content type is NOT
     * taken from the request: UploadedFile::getClientMimeType() is
     * attacker-supplied, so trusting it would let a caller register a script as
     * an image and have it replayed with a Content-Type a browser executes.
     *
     * By default the type is sniffed from the bytes. `$type` lets a caller name
     * the kind instead, for the text formats sniffing cannot see; sniffing then
     * acts as a veto rather than the source of truth. Either way the stored
     * content type comes from the AssetTypes registry. See resolveKind().
     *
     * @throws InvalidArgumentException when nothing accepts the bytes, or they
     *                                  contradict a declared `$type`
     */
    public function store(
        UploadedFile $file,
        string $slot,
        string $key,
        string $source = Asset::SOURCE_CORE,
        ?string $name = null,
        ?int $userId = null,
        string $storage = Asset::STORAGE_LOCAL,
        ?string $type = null,
    ): Asset {
        return $this->storeContents($file->get(), $slot, $key, $source, $name, $userId, $storage, $type);
    }

    /**
     * Same rules as {@see store()}, for callers that already hold the bytes —
     * a generated derivative, or a file being moved in from somewhere else.
     *
     * @throws InvalidArgumentException when the bytes' content type is not one
     *                                  this slot's kind accepts
     */
    public function storeContents(
        string $contents,
        string $slot,
        string $key,
        string $source = Asset::SOURCE_CORE,
        ?string $name = null,
        ?int $userId = null,
        string $storage = Asset::STORAGE_LOCAL,
        ?string $type = null,
    ): Asset {
        $slot = $this->validSlot($slot);
        $storage = $this->validStorage($storage);
        [$type, $extension, $contentType] = $this->resolveKind($contents, $type);

        $existing = Asset::query()->slot($slot)->where('key', $key)->first();

        // Path is keyed on a fresh unique id rather than on {slot}/{key}, so a
        // replacement never overwrites the bytes a consumer may still be
        // fetching, and two assets can never contend for one path.
        $path = Asset::PATH_PREFIX.'/'.$slot.'/'.uniqid('', true).'.'.$extension;
        $disk = Storage::disk($storage);

        $disk->put($path, $contents);

        // Explicit, matching ImageUploadService::store(). A disk that declares a
        // URL can point at S3/R2, where a bare put() takes the bucket default
        // and the URL would 403. rescue() because a local disk has no
        // per-object visibility to set.
        if (Asset::diskDeclaresUrl($storage)) {
            rescue(fn () => $disk->setVisibility($path, 'public'), report: false);
        }

        return $this->write($existing, [
            'key'          => $key,
            'slot'         => $slot,
            'type'         => $type,
            'source'       => $source,
            'name'         => $name,
            'content_type' => $contentType,
            'path'         => $path,
            'storage'      => $storage,
            'last_update'  => $this->stamp($contents),
            'size'         => strlen($contents),
            'updated_by'   => $userId,
        ]);
    }

    /**
     * Record a file that is ALREADY on the right disk as an asset, in place —
     * no copy, no new path.
     *
     * For migrating something into assets without breaking the URL it is
     * already served under: a public asset's URL is derived from its path, so
     * copying the bytes to a fresh path would silently change every URL an
     * install has already published. The file keeps its own layout; only the
     * row is new.
     *
     * @param string $path location on `$storage`
     *
     * @throws InvalidArgumentException when the file is missing, or its content
     *                                  type is not one we accept
     */
    public function adopt(
        string $path,
        string $slot,
        string $key,
        string $source = Asset::SOURCE_CORE,
        ?string $name = null,
        string $storage = Asset::STORAGE_LOCAL,
    ): Asset {
        $storage = $this->validStorage($storage);
        $disk = Storage::disk($storage);

        if (!$disk->exists($path)) {
            throw new InvalidArgumentException("No file to adopt at [{$path}].");
        }

        $slot = $this->validSlot($slot);
        $contents = (string) $disk->get($path);
        $contentType = (string) new finfo(FILEINFO_MIME_TYPE)->buffer($contents);
        [$type] = $this->kindFor($contentType);

        return $this->write(Asset::query()->slot($slot)->where('key', $key)->first(), [
            'key'          => $key,
            'slot'         => $slot,
            'type'         => $type,
            'source'       => $source,
            'name'         => $name,
            'content_type' => $contentType,
            'path'         => $path,
            'storage'      => $storage,
            'last_update'  => $this->stamp($contents),
            'size'         => strlen($contents),
        ]);
    }

    /**
     * Record an EXTERNAL URL as an asset: no bytes, no disk, `path` holds the
     * link and `storage` holds {@see Asset::STORAGE_URL}.
     *
     * Lives here rather than in the admin form that collects the URL because it
     * is the same write as {@see store()} minus the bytes — it replaces the row
     * for a (slot, key) and, through {@see write()}, deletes the file the
     * previous row pointed at. Doing that from the UI layer would mean a raw
     * model write plus a hand-rolled copy of that cleanup at every call site.
     *
     * `$type` is declared, not sniffed: there are no bytes to read. It is only
     * checked against the registry so a link cannot claim a kind nothing serves.
     * `content_type` stays null — we never saw the file, and guessing from the
     * URL's extension would be a lie the delivery layer replays as a header.
     *
     * @throws InvalidArgumentException on a bad slot, an unregistered type, or a
     *                                  URL that is not http(s)
     */
    public function storeLink(
        string $url,
        string $slot,
        string $key,
        string $source = Asset::SOURCE_CORE,
        ?string $name = null,
        ?int $userId = null,
        string $type = AssetTypes::IMAGE,
    ): Asset {
        $slot = $this->validSlot($slot);
        $url = $this->validUrl($url);

        if (app(AssetTypes::class)->canonicalFor($type) === null) {
            throw new InvalidArgumentException("No asset type [{$type}] is registered.");
        }

        return $this->write(Asset::query()->slot($slot)->where('key', $key)->first(), [
            'key'          => $key,
            'slot'         => $slot,
            'type'         => $type,
            'source'       => $source,
            'name'         => $name,
            'content_type' => null,
            'path'         => $url,
            'storage'      => Asset::STORAGE_URL,
            'last_update'  => $this->stamp($url),
            'size'         => 0,
            'updated_by'   => $userId,
        ]);
    }

    /**
     * A link is put straight into an `<img src>` by every consumer, so the
     * scheme is checked here rather than trusted: `javascript:` and `data:` are
     * script in a string, and this value arrives from an admin form.
     *
     * @throws InvalidArgumentException on anything but an absolute http(s) URL
     */
    private function validUrl(string $url): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (!in_array($scheme, ['http', 'https'], true) || blank(parse_url($url, PHP_URL_HOST))) {
            throw new InvalidArgumentException("Invalid asset URL [{$url}]: expected an absolute http or https address.");
        }

        return $url;
    }

    /**
     * A slot is a free vocabulary — modules declare their own — but not a free
     * string. It becomes a directory name and a URL segment downstream, so the
     * format is what stands in for the closed list that used to guard this: no
     * separators, no traversal, no surprises in a path.
     *
     * @throws InvalidArgumentException on anything outside [a-z0-9-]
     */
    private function validSlot(string $slot): string
    {
        if (preg_match('/^[a-z][a-z0-9-]*$/', $slot) !== 1) {
            throw new InvalidArgumentException(
                "Invalid asset slot [{$slot}]: expected lowercase letters, digits and hyphens."
            );
        }

        return $slot;
    }

    /**
     * The disk bytes may be written to.
     *
     * `url` is the sentinel meaning "this asset is a link" (Asset::STORAGE_URL),
     * so it can never name a disk here: a row written with it would claim its
     * `path` is a URL when it is a path. That is also the guard against an
     * install configuring a disk under that literal name — there would be no
     * way to tell the two apart afterwards, so the write is refused rather than
     * silently reinterpreted.
     *
     * @throws InvalidArgumentException on the sentinel or an unconfigured disk
     */
    private function validStorage(string $storage): string
    {
        if ($storage === Asset::STORAGE_URL) {
            throw new InvalidArgumentException(
                'Asset storage ['.Asset::STORAGE_URL.'] is reserved for external links and cannot name a disk.'
            );
        }

        if (!is_array(config("filesystems.disks.{$storage}"))) {
            throw new InvalidArgumentException("No filesystem disk [{$storage}] is configured.");
        }

        return $storage;
    }

    /**
     * The kind and extension for sniffed bytes.
     *
     * Rejects rather than guesses when nothing accepts the content type: a
     * wrong extension is a file the consumer cannot serve correctly, and a kind
     * nobody registered is a file nothing knows how to use.
     *
     * @return array{0: string, 1: string}
     *
     * @throws InvalidArgumentException when no registered kind accepts it
     */
    /**
     * Decide the kind, extension and stored content type for some bytes.
     *
     * Two routes, because sniffing cannot see every format:
     *
     * - **Nothing declared** — sniff and let the bytes name themselves. Right
     *   for images and audio, where the magic bytes are unambiguous, and it
     *   stays the default so the common path cannot be talked into anything.
     * - **A kind declared** — the caller says what it is storing, for the text
     *   formats sniffing reads as `text/plain` (a stylesheet always, a script
     *   often). Sniffing is not skipped: it is demoted to a veto. If the bytes
     *   sniff to a kind we recognise and it is not the declared one, the store
     *   is refused, so a PNG cannot be filed as a stylesheet. The stored
     *   content type comes from the registry either way, never from the caller.
     *
     * @return array{0: string, 1: string, 2: string} [type, extension, content type]
     *
     * @throws InvalidArgumentException when nothing accepts the bytes, the
     *                                  declared kind is unknown, or the bytes
     *                                  contradict the declared kind
     */
    private function resolveKind(string $contents, ?string $declaredType): array
    {
        $sniffed = (string) new finfo(FILEINFO_MIME_TYPE)->buffer($contents);

        if ($declaredType === null) {
            [$type, $extension] = $this->kindFor($sniffed);

            return [$type, $extension, $sniffed];
        }

        $types = app(AssetTypes::class);
        $canonical = $types->canonicalFor($declaredType);

        if ($canonical === null) {
            throw new InvalidArgumentException(
                "No asset type [{$declaredType}] is registered."
            );
        }

        // The veto. An unrecognised sniff (text/plain, and everything else we
        // do not register) says nothing either way and is allowed through —
        // that is the whole reason this route exists. A sniff we DO recognise
        // is authoritative, and disagreeing with the declaration means the
        // caller is wrong about its own bytes.
        $sniffedType = $types->typeFor($sniffed);

        if ($sniffedType !== null && $sniffedType !== $declaredType) {
            throw new InvalidArgumentException(
                "Asset declared as [{$declaredType}] but its bytes are [{$sniffed}]."
            );
        }

        return [$declaredType, $canonical[1], $canonical[0]];
    }

    private function kindFor(string $contentType): array
    {
        $types = app(AssetTypes::class);
        $type = $types->typeFor($contentType);
        $extension = $types->extensionFor($contentType);

        if ($type === null || $extension === null) {
            throw new InvalidArgumentException(
                "Unsupported asset content type [{$contentType}]."
            );
        }

        return [$type, $extension];
    }

    /**
     * Create or replace the row for a (slot, key), cleaning up the file the old
     * row pointed at.
     *
     * @param array<string, mixed> $attributes
     */
    private function write(?Asset $existing, array $attributes): Asset
    {
        if (!$existing instanceof Asset) {
            return Asset::create($attributes);
        }

        // Captured before the update, because a replacement may move the asset
        // to another disk, or turn it into a link — deleting from the NEW
        // storage would leave the old file behind, and on a disk with a URL
        // that means bytes still reachable.
        $previousDisk = $existing->diskName();
        $previousPath = $existing->path;
        $wasLink = $existing->isLink();

        $existing->update($attributes);

        // Done after the row points at the new file, so a failure above leaves
        // the old bytes reachable rather than the row dangling. Skipped when
        // the row still points at the same file on the same disk, which is what
        // an adopt() of an already-adopted path does — deleting there would
        // destroy the asset. A link had no file to begin with.
        $moved = $previousPath !== $existing->path || $previousDisk !== $existing->diskName();

        if (!$wasLink && $moved) {
            Storage::disk($previousDisk)->delete($previousPath);
        }

        return $existing->refresh();
    }

    /**
     * The change stamp consumers compare. A content hash rather than a
     * timestamp, so re-uploading identical bytes does not force every client to
     * re-download; consumers compare it for equality only and never order it,
     * which is what lets this be a hash at all.
     *
     * crc32b, the same algorithm the retired `airlines.logo_hash` column used,
     * so an airline mark reads the same shape of stamp after moving here.
     */
    public function stamp(string $contents): string
    {
        return hash('crc32b', $contents);
    }

    /**
     * @return Collection<int, Asset>
     */
    public function list(?string $slot = null, ?string $type = null, ?string $source = null): Collection
    {
        return Asset::query()
            ->when($slot !== null, fn ($q) => $q->slot($slot))
            ->when($type !== null, fn ($q) => $q->type($type))
            ->when($source !== null, fn ($q) => $q->source($source))
            ->orderBy('slot')
            ->orderBy('key')
            ->get();
    }

    public function find(string $slot, string $key): ?Asset
    {
        return Asset::query()->slot($slot)->where('key', $key)->first();
    }
}
