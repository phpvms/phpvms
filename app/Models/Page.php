<?php

namespace App\Models;

use App\Contracts\Model;
use App\Enums\PageType;
use App\Exceptions\UnknownPageType;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int         $id
 * @property string      $name
 * @property string      $slug
 * @property string|null $icon
 * @property PageType    $type
 * @property bool        $public
 * @property bool        $enabled
 * @property string|null $body
 * @property string|null $link
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property bool        $new_window
 * @property-read mixed $url
 *
 * @method static Builder<static>|Page bySlug(string $slug)
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
class Page extends Model
{
    use HasSlug;

    public $table = 'pages';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'icon',
        'public',
        'body',
        'link',
        'enabled',
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
                PageType::PAGE => url(route('frontend.pages.show', ['slug' => $this->slug])),
                PageType::LINK => $this->link,
            }
        );
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'type'       => PageType::class,
            'public'     => 'boolean',
            'enabled'    => 'boolean',
            'new_window' => 'boolean',
        ];
    }

    #[Scope]
    protected function bySlug(Builder $q, string $slug): Builder
    {
        return $q->where('slug', $slug);
    }
}
