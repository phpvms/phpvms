<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class FlightFieldValue.
 *
 * @property string   flight_id
 * @property string   name
 * @property string   value
 */
class FlightFieldValue extends Model
{
    public $table = 'flight_field_values';

    protected $fillable = [
        'flight_id',
        'name',
        'slug',
        'value',
    ];

    public static $rules = [];

    /**
     * When setting the name attribute, also set the slug.
     */
    public function name(): Attribute
    {
        return Attribute::make(
            set: fn ($name) => [
                'name' => $name,
                'slug' => str_slug($name),
            ]
        );
    }

    /**
     * Relationships.
     */
    public function flight(): BelongsTo
    {
        return $this->belongsTo(Flight::class, 'flight_id');
    }
}
