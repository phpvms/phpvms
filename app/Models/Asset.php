<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Model;
use App\Features\Assets\AssetService;
use App\Features\Assets\AssetTypes;
use App\Traits\HasAssets;
use App\Traits\HasNanoIds;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Override;

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
 * @property string      $slot         who consumes it; core owns SLOT_*, modules declare their own
 * @property string      $type         what the bytes are; see AssetTypes
 * @property string      $source       owning module slug; 'phpvms' for phpVMS itself
 * @property string|null $name
 * @property string|null $content_type null for a link, whose bytes we never saw
 * @property string      $path         location on the asset's disk, or the URL itself when storage is STORAGE_URL
 * @property string      $storage      disk name from config('filesystems.disks'), or STORAGE_URL
 * @property string      $last_update  opaque change stamp — compared for equality only
 * @property int         $size
 * @property int|null    $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $editor
 *
 * @method static Builder<static>|Asset whereStorage($value)
 * @method static Builder<static>|Asset newModelQuery()
 * @method static Builder<static>|Asset newQuery()
 * @method static Builder<static>|Asset query()
 * @method static Builder<static>|Asset slot(string $slot)
 * @method static Builder<static>|Asset source(string $source)
 * @method static Builder<static>|Asset type(string $type)
 * @method static Builder<static>|Asset whereContentType($value)
 * @method static Builder<static>|Asset whereCreatedAt($value)
 * @method static Builder<static>|Asset whereId($value)
 * @method static Builder<static>|Asset whereKey($value)
 * @method static Builder<static>|Asset whereLastUpdate($value)
 * @method static Builder<static>|Asset whereName($value)
 * @method static Builder<static>|Asset wherePath($value)
 * @method static Builder<static>|Asset whereSize($value)
 * @method static Builder<static>|Asset whereSlot($value)
 * @method static Builder<static>|Asset whereSource($value)
 * @method static Builder<static>|Asset whereType($value)
 * @method static Builder<static>|Asset whereUpdatedAt($value)
 * @method static Builder<static>|Asset whereUpdatedBy($value)
 *
 * @mixin \Eloquent
 */
class Asset extends Model
{
    use HasNanoIds;

    /**
     * The reserved `storage` value meaning "this asset is an external link":
     * there are no bytes on any disk and `path` holds the URL itself.
     *
     * It shares the column with real disk names, so a disk configured under
     * this literal name would be ambiguous; AssetService rejects one at write
     * time.
     */
    public const STORAGE_URL = 'url';

    /**
     * The disk with no URL of its own: assets on it are reachable only through
     * an authenticated route. Also where an admin form stages an upload before
     * it becomes an asset.
     */
    public const STORAGE_LOCAL = 'local';

    public const PATH_PREFIX = 'assets';

    public const SOURCE_CORE = 'phpvms';

    /**
     * The slots core itself consumes. A slot is a plain string, not an enum,
     * for the same reason `source` is: a module cannot add a case to a PHP enum
     * core ships, and slots like sounds or paintkits belong to whatever module
     * serves them, not here. Core declares only its own and validates the
     * format (see AssetService), because a slot becomes a directory name and a
     * URL segment downstream.
     */
    public const SLOT_BRANDING = 'branding';

    public const SLOT_AIRLINE_LOGO = 'airline-logo';

    /** A rank's badge, keyed on the rank id. */
    public const SLOT_RANK = 'rank';

    /** A flight bundle's hero image (tours page), keyed on the bundle id. */
    public const SLOT_BUNDLE = 'flight-bundle';

    /** An award's badge, keyed on the award id. */
    public const SLOT_AWARD = 'award';

    /**
     * A user's own images — today just the avatar. Keyed on the user id, not on
     * the literal name of the image: `(slot, key)` is unique, so a flat
     * `avatar` key would let one pilot's picture occupy the slot for everyone.
     * The ACARS contract still shows a client `key = "avatar"`, because a
     * client is only ever handed its own user.
     */
    public const SLOT_USER = 'user';

    public $table = 'assets';

    protected $fillable = [
        'key',
        'slot',
        'type',
        'source',
        'name',
        'content_type',
        'path',
        'storage',
        'last_update',
        'size',
        'updated_by',
    ];

    /**
     * The attributes that should be casted to native types.
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // AssetService::find() memoises per (slot, key) for the life of a
        // request. Both hooks fire for a write made through the service (its
        // store()/storeContents()/storeLink()/adopt() all persist through
        // Eloquent) and for one made directly against the model, so either
        // route sees the memo cleared before the next find().
        static::saved(function (self $asset): void {
            app(AssetService::class)->forgetMemo($asset->slot, $asset->key);
        });

        // The file IS the asset. Leaving it behind orphans bytes nothing owns —
        // and on a disk with a URL, bytes that are still reachable.
        static::deleted(function (self $asset): void {
            app(AssetService::class)->forgetMemo($asset->slot, $asset->key);

            // A link asset owns no bytes, so there is nothing to delete and its
            // `path` is not a path.
            if ($asset->isLink()) {
                return;
            }

            Storage::disk($asset->diskName())->delete($asset->path);
        });
    }

    /** The disk this asset's bytes live on. */
    public function diskName(): string
    {
        return $this->storage;
    }

    /** Whether this asset references an external URL rather than stored bytes. */
    public function isLink(): bool
    {
        return $this->storage === self::STORAGE_URL;
    }

    /**
     * Whether a disk declares an address its files are served at, which is what
     * makes an asset on it linkable rather than something to stream.
     *
     * `filled()` and not `isset()`: every s3-family disk here declares the key
     * with a possibly-empty value — `env('AWS_URL')` (config/filesystems.php:86)
     * and `env('CLOUDFLARE_R2_URL', '')` (:99) — and Storage would happily build
     * a bare `/assets/x.png` out of one. An operator setting that env var is the
     * declaration that the bucket is served there.
     */
    public static function diskDeclaresUrl(string $disk): bool
    {
        return filled(config("filesystems.disks.{$disk}.url"));
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /*
     * Query scopes
     */
    #[Scope]
    protected function slot(Builder $query, string $slot): Builder
    {
        return $query->where('slot', $slot);
    }

    #[Scope]
    protected function type(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    #[Scope]
    protected function source(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    /**
     * The URL this asset can be linked at directly, or null when it has none.
     *
     * Null is not an error — it means "you have to stream these bytes
     * yourself". Core does that through `api.assets.show`; a module does it
     * through its own endpoint. Each consumer knows its own audience, so the
     * asset does not choose for them.
     *
     * Reachability is read from the disk's `url` config entry rather than
     * stored; see {@see diskDeclaresUrl()}.
     */
    public function url(): ?string
    {
        if ($this->isLink()) {
            return $this->path;
        }

        return self::diskDeclaresUrl($this->storage)
            ? Storage::disk($this->storage)->url($this->path)
            : null;
    }

    /**
     * The URL of the asset at (slot, key), or null when there is no such asset
     * or it has none — see {@see url()} for what null means.
     *
     * The global lookup: both arguments are supplied by the caller, so no model
     * is involved. A model that owns its key uses {@see HasAssets}.
     */
    public static function getUrl(string $slot, string $key): ?string
    {
        return app(AssetService::class)->find($slot, $key)?->url();
    }

    /**
     * Filename a consumer stores this under: {key}.{ext}, the extension derived
     * from content_type — or the bare key when there is no content type to
     * derive one from, which is the normal case for a link.
     *
     * Keyed on `key` and not `id` on purpose: a nano ID here would make an
     * on-disk cache undebuggable, and anything referencing an asset by filename
     * (a SOP sound action, say) unreadable.
     */
    public function filename(): string
    {
        $ext = app(AssetTypes::class)->extensionFor((string) $this->content_type);

        return $ext === null ? $this->key : $this->key.'.'.$ext;
    }
}
