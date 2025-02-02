<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

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
                'slug' => \Illuminate\Support\Str::slug($name),
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
