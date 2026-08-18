<?php

declare(strict_types=1);

namespace App\Features\Assets\Models;

use App\Contracts\Model;
use App\Features\Assets\Enums\AssetSlot;
use App\Features\Assets\Enums\AssetType;
use App\Models\User;
use App\Traits\HasNanoIds;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * One downloadable blob — a branding image, an uploaded sound, a stylesheet, a
 * web component, an aircraft paintkit. Replaces the pattern where each new kind
 * of file meant new settings keys plus a new delivery shape per consumer.
 *
 * `id` addresses the bytes and never changes; `key` is the human name a
 * consumer looks up by, unique with `slot` across every source (see the
 * migration for why source is not part of that key).
 *
 * Not soft-deleted: an asset owns a file and a slice of the (slot, key)
 * namespace, and holding either after deletion is a bug.
 *
 * @property string      $id
 * @property string      $key
 * @property AssetSlot   $slot
 * @property AssetType   $type
 * @property string      $source       owning module slug; 'core' for phpVMS itself
 * @property string|null $name
 * @property string      $content_type
 * @property string      $path         location on the private disk
 * @property bool        $is_public    served without authentication
 * @property string      $last_update  opaque change stamp — compared for equality only
 * @property int         $size
 * @property int|null    $updated_by
 * @property User|null   $editor
 */
class Asset extends Model
{
    use HasNanoIds;

    /**
     * Where an asset's bytes live is decided by `is_public`, because that is
     * what decides how they are served.
     *
     * A public asset — site branding, an airline mark — is served as a plain
     * storage URL off the public disk. It is public information rendered on
     * pages a logged-out visitor sees, so routing it through PHP would add a
     * request hop and gain nothing.
     *
     * Everything else sits on the private disk with no URL of its own, and is
     * reachable only through the authenticated API endpoint.
     */
    public const PRIVATE_DISK = 'local';

    public const PATH_PREFIX = 'assets';

    /** Staging for in-progress uploads. Always private, whatever they become. */
    public const STAGING_DISK = self::PRIVATE_DISK;

    public const SOURCE_CORE = 'core';

    public $table = 'assets';

    public $fillable = [
        'key',
        'slot',
        'type',
        'source',
        'name',
        'content_type',
        'path',
        'is_public',
        'last_update',
        'size',
        'updated_by',
    ];

    protected $casts = [
        'slot'      => AssetSlot::class,
        'type'      => AssetType::class,
        'is_public' => 'boolean',
        'size'      => 'integer',
    ];

    protected static function booted(): void
    {
        // The file IS the asset. Leaving it behind orphans bytes nothing owns —
        // and on the public disk, bytes that are still reachable by URL.
        static::deleted(function (self $asset): void {
            Storage::disk($asset->diskName())->delete($asset->path);
        });
    }

    /** The disk this asset's bytes live on, decided by `is_public`. */
    public function diskName(): string
    {
        return self::diskFor((bool) $this->is_public);
    }

    public static function diskFor(bool $isPublic): string
    {
        return $isPublic ? (string) config('filesystems.public_files') : self::PRIVATE_DISK;
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeSlot(Builder $query, AssetSlot $slot): Builder
    {
        return $query->where('slot', $slot->value);
    }

    public function scopeType(Builder $query, AssetType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function scopeSource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    /**
     * Where this asset's bytes are fetched from.
     *
     * A public asset gets its storage URL directly — no PHP in the path, and
     * the same shape airline logos have always had. A private one gets the
     * authenticated API endpoint, addressed by id and stable across a
     * replacement, which is why that endpoint revalidates rather than caching
     * immutably.
     */
    public function url(): string
    {
        return $this->is_public
            ? Storage::disk($this->diskName())->url($this->path)
            : route('api.assets.show', $this);
    }

    /**
     * Filename a consumer stores this under: {key}.{ext}, the extension derived
     * from content_type.
     *
     * Keyed on `key` and not `id` on purpose: a nano ID here would make an
     * on-disk cache undebuggable, and anything referencing an asset by filename
     * (a SOP sound action, say) unreadable.
     */
    public function filename(): string
    {
        $ext = $this->type->extensionFor($this->content_type);

        return $ext === null ? $this->key : $this->key.'.'.$ext;
    }
}
