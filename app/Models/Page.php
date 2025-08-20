<?php

namespace App\Models;

use App\Contracts\Model;
use App\Exceptions\UnknownPageType;
use App\Models\Enums\PageType;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property int                             $id
 * @property string                          $name
 * @property string                          $slug
 * @property string|null                     $icon
 * @property int                             $type
 * @property bool                            $public
 * @property bool                            $enabled
 * @property string|null                     $body
 * @property string|null                     $link
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property bool                            $new_window
 * @property-read mixed $url
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Page newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Page newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Page query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Page whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Page whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Page whereEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Page whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Page whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Page whereLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Page whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Page whereNewWindow($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Page wherePublic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Page whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Page whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Page whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Page extends Model
{
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
            get: function ($value, $attrs) {
                if ($this->type === PageType::PAGE) {
                    return url(route('frontend.pages.show', ['slug' => $this->slug]));
                }

                if ($this->type === PageType::LINK) {
                    return $this->link;
                }

                throw new UnknownPageType($this);
            }
        );
    }

    protected function casts(): array
    {
        return [
            'type'       => 'integer',
            'public'     => 'boolean',
            'enabled'    => 'boolean',
            'new_window' => 'boolean',
        ];
    }
}
