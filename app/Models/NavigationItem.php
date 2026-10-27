<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Model;
use App\Enums\NavigationTarget;
use Database\Factories\NavigationItemFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Override;

/**
 * One entry in the site navigation.
 *
 * Deliberately not a page. An item may point at a page, a named route, or an
 * external URL, and a page may have no item at all — which is what makes an
 * unlisted landing page possible.
 *
 * @property int              $id
 * @property string|null      $name
 * @property string           $label
 * @property string|null      $icon
 * @property int|null         $parent_id
 * @property int              $sort
 * @property NavigationTarget $target_type
 * @property string|null      $target_value
 * @property bool             $new_window
 * @property bool             $public
 * @property bool             $visible
 * @property Carbon|null      $created_at
 * @property Carbon|null      $updated_at
 * @property-read NavigationItem|null     $parent
 * @property-read Collection<int, static> $children
 *
 * @method static Builder<static>|NavigationItem newModelQuery()
 * @method static Builder<static>|NavigationItem newQuery()
 * @method static Builder<static>|NavigationItem query()
 * @method static Builder<static>|NavigationItem ordered()
 * @method static Builder<static>|NavigationItem visibleTo(bool $authenticated)
 *
 * @mixin \Eloquent
 */
class NavigationItem extends Model
{
    /** @use HasFactory<NavigationItemFactory> */
    use HasFactory;

    public $table = 'navigation_items';

    protected $fillable = [
        'name',
        'label',
        'icon',
        'parent_id',
        'sort',
        'target_type',
        'target_value',
        'new_window',
        'public',
        'visible',
    ];

    public static array $rules = [
        'name'        => 'required',
        'label'       => 'required',
        'target_type' => 'required',
    ];

    /**
     * The parent_id foreign key cascades, so deleting an item that still has
     * children would silently delete its whole subtree. Refuse instead; the
     * item has to be emptied first.
     */
    #[Override]
    protected static function booted(): void
    {
        static::deleting(fn (NavigationItem $item): bool => !$item->children()->exists());
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'target_type' => NavigationTarget::class,
            'new_window'  => 'boolean',
            'public'      => 'boolean',
            'visible'     => 'boolean',
            'sort'        => 'integer',
        ];
    }

    /**
     * @return BelongsTo<NavigationItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<NavigationItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }

    /**
     * Resolve the item to a href.
     *
     * Returns null rather than throwing when the target has gone missing — a
     * deleted page or a renamed route must not take the whole menu down with it.
     * Callers drop null items.
     */
    public function resolveUrl(): ?string
    {
        if (blank($this->target_value)) {
            return null;
        }

        return match ($this->target_type) {
            NavigationTarget::URL   => $this->target_value,
            NavigationTarget::ROUTE => Route::has($this->target_value)
                ? route($this->target_value)
                : null,
            NavigationTarget::PAGE => Page::query()
                ->whereKey($this->target_value)
                ->first()?->url,
        };
    }

    #[Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('id');
    }

    /**
     * Mirrors the guest filter `PageLinksComposer` has always applied: everyone
     * sees public items, authenticated users see the rest too.
     */
    #[Scope]
    protected function visibleTo(Builder $query, bool $authenticated): Builder
    {
        $query->where('visible', true);

        if (!$authenticated) {
            $query->where('public', true);
        }

        return $query;
    }
}
