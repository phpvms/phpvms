<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

/**
 * Class FlightField
 *
 * @property string name
 * @property string slug
 * @property bool   required
 */
class FlightField extends Model
{
    public $table = 'flight_fields';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'required',
    ];

    public static $rules = [
        'name' => 'required',
    ];

    /**
     * When setting the name attribute, also set the slug
     */
    public function name(): Attribute
    {
        return Attribute::make(
            set: fn ($name) => [
                'name' => $name,
                'slug' => Str::slug($name),
            ]
        );
    }

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
        ];
    }
}
