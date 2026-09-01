<?php

namespace App\Models;

use App\Contracts\Model;
use App\Enums\PageStatus;
use App\Enums\PageType;
use App\Exceptions\UnknownPageType;
use App\Observers\PageObserver;
use App\Traits\HasSlug;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int         $id
 * @property string      $name
 * @property string      $slug
 * @property string|null $path
 * @property string|null $icon
 * @property PageType    $type
 * @property bool        $public
 * @property bool        $enabled
 * @property PageStatus  $status
 * @property string|null $body
 * @property string|null $draft_body
 * @property Carbon|null $published_at
 * @property string|null $link
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property bool        $new_window
 * @property-read mixed $url
 * @property-read Collection<int, PageRevision> $revisions
 *
 * @method static Builder<static>|Page bySlug(string $slug)
 * @method static Builder<static>|Page byPath(string $path)
 * @method static Builder<static>|Page live()
 * @method static Builder<static>|Page newModelQuery()
 * @method static Builder<static>|Page newQuery()
 * @method static Builder<static>|Page query()
 * @method static Builder<static>|Page whereBody($value)
 * @method static Builder<static>|Page whereCreatedAt($value)
 * @method static Builder<static>|Page whereEnabled($value)
 * @method static Builder<static>|Page whereIcon($value)
 * @method static Builder<static>|Page whereId($value)
 * @method static Builder<static>|Page whereLink($value)
 * @method static Builder<static>|Page whereName($value)
 * @method static Builder<static>|Page whereNewWindow($value)
 * @method static Builder<static>|Page wherePublic($value)
 * @method static Builder<static>|Page whereSlug($value)
 * @method static Builder<static>|Page whereType($value)
 * @method static Builder<static>|Page whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[ObservedBy(PageObserver::class)]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    use HasSlug;

    public $table = 'pages';

    /**
     * The DB column defaults to DRAFT, but that default only applies on insert,
     * so an unsaved or just-created model reported a null status until it was
     * reloaded. Mirroring it here keeps `$page->status` answerable at all times.
     */
    protected $attributes = [
        'status' => 0, // PageStatus::DRAFT
    ];

    protected $fillable = [
        'name',
        'slug',
        'path',
        'type',
        'icon',
        'public',
        'body',
        'draft_body',
        'link',
        'enabled',
        'status',
        'published_at',
        'new_window',
    ];

    public static array $rules = [
        'name' => 'required|unique:pages,name',
        'body' => 'nullable',
        'type' => 'required',
    ];

    /**
     * Return the full URL to this page; determines if it's internal or external
     *
     * @throws UnknownPageType
     */
    public function url(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->type) {
                // A claimed path wins over the legacy slug route, but the slug
                // route keeps working either way so existing links survive.
                PageType::PAGE => filled($this->path)
                    ? url($this->path)
                    : url(route('frontend.pages.show', ['slug' => $this->slug])),
                PageType::LINK => $this->link,
            }
        );
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'type'         => PageType::class,
            'status'       => PageStatus::class,
            'public'       => 'boolean',
            'enabled'      => 'boolean',
            'new_window'   => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PageRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class, 'page_id')->latest('published_at');
    }

    #[Scope]
    protected function bySlug(Builder $q, string $slug): Builder
    {
        return $q->where('slug', $slug);
    }

    #[Scope]
    protected function byPath(Builder $q, string $path): Builder
    {
        return $q->where('path', static::normalisePath($path));
    }

    /**
     * Everything a visitor is allowed to reach.
     *
     * Both switches must be satisfied. `enabled` is the admin's on/off toggle
     * and was never enforced on the show route until now; `status` is whether
     * the content has ever been published. A page can be enabled and still be an
     * unpublished draft.
     */
    #[Scope]
    protected function live(Builder $q): Builder
    {
        return $q->where('enabled', true)->where('status', PageStatus::PUBLISHED);
    }

    /**
     * One spelling for a claimed path, so `/about`, `about` and `about/` cannot
     * become three different claims on the same URL.
     *
     * The root is the one path that stays as a bare slash.
     */
    public static function normalisePath(string $path): string
    {
        $trimmed = trim(trim($path), '/');

        return $trimmed === '' ? '/' : '/'.$trimmed;
    }
}
