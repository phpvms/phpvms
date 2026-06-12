<?php

namespace App\Models;

use App\Contracts\Model;
use App\Traits\HasSlug;
use Database\Factories\FlightFieldValueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Class FlightFieldValue
 *
 * @property int         $id
 * @property string      $flight_id
 * @property string      $name
 * @property string|null $slug
 * @property string|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Flight|null $flight
 *
 * @method static FlightFieldValueFactory          factory($count = null, $state = [])
 * @method static Builder<static>|FlightFieldValue newModelQuery()
 * @method static Builder<static>|FlightFieldValue newQuery()
 * @method static Builder<static>|FlightFieldValue query()
 * @method static Builder<static>|FlightFieldValue whereCreatedAt($value)
 * @method static Builder<static>|FlightFieldValue whereFlightId($value)
 * @method static Builder<static>|FlightFieldValue whereId($value)
 * @method static Builder<static>|FlightFieldValue whereName($value)
 * @method static Builder<static>|FlightFieldValue whereSlug($value)
 * @method static Builder<static>|FlightFieldValue whereUpdatedAt($value)
 * @method static Builder<static>|FlightFieldValue whereValue($value)
 *
 * @mixin \Eloquent
 */
class FlightFieldValue extends Model
{
    use HasFactory;
    use HasSlug;

    public $table = 'flight_field_values';

    protected $fillable = [
        'flight_id',
        'name',
        'slug',
        'value',
    ];

    public static array $rules = [];

    /**
     * When setting the name attribute, also set the slug
     */
    public function name(): Attribute
    {
        return Attribute::make(
            set: fn ($name): array => [
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
