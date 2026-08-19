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
     * `$isPublic` decides whether the serving route lets a logged-out request
     * through, and defaults closed. Site branding needs it open — it renders on
     * the login screen — while sounds and paintkits do not.
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
        bool $isPublic = false,
        ?string $type = null,
    ): Asset {
        return $this->storeContents($file->get(), $slot, $key, $source, $name, $userId, $isPublic, $type);
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
        bool $isPublic = false,
        ?string $type = null,
    ): Asset {
        $slot = $this->validSlot($slot);
        [$type, $extension, $contentType] = $this->resolveKind($contents, $type);

        $existing = Asset::query()->slot($slot)->where('key', $key)->first();

        // Path is keyed on a fresh unique id rather than on {slot}/{key}, so a
        // replacement never overwrites the bytes a consumer may still be
        // fetching, and two assets can never contend for one path.
        $path = Asset::PATH_PREFIX.'/'.$slot.'/'.uniqid('', true).'.'.$extension;
        $disk = Storage::disk(Asset::diskFor($isPublic));

        $disk->put($path, $contents);

        // Explicit, matching ImageUploadService::store(). The public disk can
        // point at S3/R2, where a bare put() takes the bucket default and the
        // URL would 403. rescue() because a local disk has no per-object
        // visibility to set.
        if ($isPublic) {
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
            'is_public'    => $isPublic,
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
     * @param string $path location on the disk `$isPublic` implies
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
        bool $isPublic = false,
    ): Asset {
        $disk = Storage::disk(Asset::diskFor($isPublic));

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
            'is_public'    => $isPublic,
            'last_update'  => $this->stamp($contents),
            'size'         => strlen($contents),
        ]);
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

        // Captured before the update, because a replacement may flip is_public
        // and move the asset to the other disk — deleting from the NEW disk
        // would leave the old file behind, and on the public disk that means
        // bytes still reachable by URL.
        $previousDisk = $existing->diskName();
        $previousPath = $existing->path;

        $existing->update($attributes);

        // Done after the row points at the new file, so a failure above leaves
        // the old bytes reachable rather than the row dangling. Skipped when
        // the row still points at the same file, which is what an adopt() of an
        // already-adopted path does — deleting there would destroy the asset.
        if ($previousPath !== $existing->path) {
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
