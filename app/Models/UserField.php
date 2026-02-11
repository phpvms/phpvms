<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

/**
 * @property int                             $id
 * @property string                          $name
 * @property string|null                     $description
 * @property bool|null                       $show_on_registration
 * @property bool|null                       $required
 * @property bool|null                       $private
 * @property bool                            $internal
 * @property bool|null                       $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $slug
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserField newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserField newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserField query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserField whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserField whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserField whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserField whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserField whereInternal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserField whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserField wherePrivate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserField whereRequired($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserField whereShowOnRegistration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserField whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class UserField extends Model
{
    public $table = 'user_fields';

    protected $fillable = [
        'name',
        'description',
        'show_on_registration', // Show on the registration form?
        'required',             // Required to be filled out in registration?
        'private',              // Whether this is shown on the user's public profile
        'internal',             // Whether this field is for internal use only (e.g. modules)
        'active',
    ];

    public static array $rules = [
        'name'        => 'required',
        'description' => 'nullable',
    ];

    /**
     * Get the slug so we can use it in forms
     */
    public function slug(): Attribute
    {
        return Attribute::make(
            get: fn ($_, $attrs) => Str::slug($attrs['name'], '_')
        );
    }

    protected function casts(): array
    {
        return [
            'show_on_registration' => 'boolean',
            'required'             => 'boolean',
            'private'              => 'boolean',
            'internal'             => 'boolean',
            'active'               => 'boolean',
        ];
    }
}
