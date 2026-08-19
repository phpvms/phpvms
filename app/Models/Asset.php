<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Model;
use App\Features\Assets\AssetTypes;
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
 * @property string      $content_type
 * @property string      $path         location on the asset's disk
 * @property bool        $is_public    served without authentication
 * @property string      $last_update  opaque change stamp — compared for equality only
 * @property int         $size
 * @property int|null    $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $editor
 *
 * @method static Builder<static>|Asset whereIsPublic($value)
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
        'is_public',
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
            'is_public' => 'boolean',
            'size'      => 'integer',
        ];
    }

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
        $ext = app(AssetTypes::class)->extensionFor((string) $this->content_type);

        return $ext === null ? $this->key : $this->key.'.'.$ext;
    }
}
