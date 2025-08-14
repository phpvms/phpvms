<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Class FlightFieldValue
 *
 * @property int                             $id
 * @property string                          $flight_id
 * @property string                          $name
 * @property string|null                     $slug
 * @property string|null                     $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Flight|null $flight
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightFieldValue newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightFieldValue newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightFieldValue query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightFieldValue whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightFieldValue whereFlightId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightFieldValue whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightFieldValue whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightFieldValue whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightFieldValue whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightFieldValue whereValue($value)
 *
 * @mixin \Eloquent
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

    /**
     * Relationships
     */
    public function flight(): BelongsTo
    {
        return $this->belongsTo(Flight::class, 'flight_id');
    }
}
