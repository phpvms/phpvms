<?php

declare(strict_types=1);

namespace App\Features\Assets;

use App\Features\Assets\Enums\AssetSlot;
use App\Features\Assets\Enums\AssetType;
use App\Features\Assets\Models\Asset;
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
     * The uploader chooses only slot, key and the bytes. The content type is
     * sniffed from the file itself rather than trusted from the request:
     * UploadedFile::getClientMimeType() is attacker-supplied, so a caller could
     * otherwise register a script as an image and have it replayed with a
     * Content-Type that makes a browser run it.
     *
     * @throws InvalidArgumentException when the file's real content type is not
     *                                  one this slot's kind accepts
     */
    public function store(
        UploadedFile $file,
        AssetSlot $slot,
        string $key,
        string $source = Asset::SOURCE_CORE,
        ?string $name = null,
        ?int $userId = null,
        bool $isPublic = false,
    ): Asset {
        return $this->storeContents($file->get(), $slot, $key, $source, $name, $userId, $isPublic);
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
        AssetSlot $slot,
        string $key,
        string $source = Asset::SOURCE_CORE,
        ?string $name = null,
        ?int $userId = null,
        bool $isPublic = false,
    ): Asset {
        $contentType = (string) new finfo(FILEINFO_MIME_TYPE)->buffer($contents);
        $type = AssetType::forContentType($contentType);

        if (!$type instanceof AssetType) {
            throw new InvalidArgumentException(
                "Unsupported asset content type [{$contentType}]."
            );
        }

        $extension = $type->extensionFor($contentType);
        $existing = Asset::query()->slot($slot)->where('key', $key)->first();

        // Path is keyed on a fresh unique id rather than on {slot}/{key}, so a
        // replacement never overwrites the bytes a consumer may still be
        // fetching, and two assets can never contend for one path.
        $path = Asset::PATH_PREFIX.'/'.$slot->value.'/'.uniqid('', true).'.'.$extension;
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
        AssetSlot $slot,
        string $key,
        string $source = Asset::SOURCE_CORE,
        ?string $name = null,
        bool $isPublic = false,
    ): Asset {
        $disk = Storage::disk(Asset::diskFor($isPublic));

        if (!$disk->exists($path)) {
            throw new InvalidArgumentException("No file to adopt at [{$path}].");
        }

        $contents = (string) $disk->get($path);
        $contentType = (string) new finfo(FILEINFO_MIME_TYPE)->buffer($contents);
        $type = AssetType::forContentType($contentType);

        if (!$type instanceof AssetType) {
            throw new InvalidArgumentException(
                "Unsupported asset content type [{$contentType}]."
            );
        }

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
    public function list(?AssetSlot $slot = null, ?AssetType $type = null, ?string $source = null): Collection
    {
        return Asset::query()
            ->when($slot instanceof AssetSlot, fn ($q) => $q->slot($slot))
            ->when($type instanceof AssetType, fn ($q) => $q->type($type))
            ->when($source !== null, fn ($q) => $q->source($source))
            ->orderBy('slot')
            ->orderBy('key')
            ->get();
    }

    public function find(AssetSlot $slot, string $key): ?Asset
    {
        return Asset::query()->slot($slot)->where('key', $key)->first();
    }
}
