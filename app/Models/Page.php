<?php

namespace App\Models;

use App\Contracts\Model;
use App\Exceptions\UnknownPageType;
use App\Models\Enums\PageType;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property int    id
 * @property string name
 * @property string slug
 * @property string icon
 * @property int    type
 * @property bool   public
 * @property bool   enabled
 * @property bool   new_window
 * @property string body
 * @property string link
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

    protected $casts = [
        'type'       => 'integer',
        'public'     => 'boolean',
        'enabled'    => 'boolean',
        'new_window' => 'boolean',
    ];

    public static $rules = [
        'name' => 'required|unique:pages,name',
        'body' => 'nullable',
        'type' => 'required',
    ];

    /**
     * Return the full URL to this page; determines if it's internal or external
     *
     * @throws \App\Exceptions\UnknownPageType
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
}
