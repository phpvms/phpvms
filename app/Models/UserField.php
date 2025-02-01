<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property string name
 * @property string slug
 * @property string value  Only set if "squashed"
 * @property bool   show_on_registration
 * @property bool   required
 * @property bool   private
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

    public static $rules = [
        'name'        => 'required',
        'description' => 'nullable',
    ];

    /**
     * Get the slug so we can use it in forms
     */
    public function slug(): Attribute
    {
        return Attribute::make(
            get: fn ($_, $attrs) => \Illuminate\Support\Str::slug($attrs['name'], '_')
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
