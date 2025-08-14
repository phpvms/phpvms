<?php

namespace App\Models;

use App\Contracts\Model;
use App\Models\Casts\CommaDelimitedCast;
use App\Models\Traits\ReferenceTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int                                                $id
 * @property int|null                                           $airline_id
 * @property string                                             $name
 * @property int                                                $amount
 * @property string                                             $type
 * @property mixed|null                                         $flight_type
 * @property int|null                                           $charge_to_user
 * @property int|null                                           $multiplier
 * @property int|null                                           $active
 * @property \Illuminate\Database\Eloquent\Model|\Eloquent|null $ref_model
 * @property string|null                                        $ref_model_id
 * @property \Illuminate\Support\Carbon|null                    $created_at
 * @property \Illuminate\Support\Carbon|null                    $updated_at
 * @property-read \App\Models\Airline|null $airline
 *
 * @method static \Database\Factories\ExpenseFactory factory($count = null, $state = [])
 * @method static Builder<static>|Expense            newModelQuery()
 * @method static Builder<static>|Expense            newQuery()
 * @method static Builder<static>|Expense            query()
 * @method static Builder<static>|Expense            whereActive($value)
 * @method static Builder<static>|Expense            whereAirlineId($value)
 * @method static Builder<static>|Expense            whereAmount($value)
 * @method static Builder<static>|Expense            whereChargeToUser($value)
 * @method static Builder<static>|Expense            whereCreatedAt($value)
 * @method static Builder<static>|Expense            whereFlightType($value)
 * @method static Builder<static>|Expense            whereId($value)
 * @method static Builder<static>|Expense            whereMultiplier($value)
 * @method static Builder<static>|Expense            whereName($value)
 * @method static Builder<static>|Expense            whereRefModel($value)
 * @method static Builder<static>|Expense            whereRefModelId($value)
 * @method static Builder<static>|Expense            whereType($value)
 * @method static Builder<static>|Expense            whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Expense extends Model
{
    use HasFactory;
    use ReferenceTrait;

    public $table = 'expenses';

    protected $fillable = [
        'airline_id',
        'name',
        'amount',
        'type',
        'flight_type',
        'multiplier',
        'charge_to_user',
        'ref_model',
        'ref_model_id',
        'active',
    ];

    public $casts = [
        'flight_type' => CommaDelimitedCast::class,
    ];

    public static array $rules = [
        'active'         => 'bool',
        'airline_id'     => 'integer',
        'amount'         => 'float',
        'multiplier'     => 'bool',
        'charge_to_user' => 'bool',
    ];

    /**
     * Relationships
     */
    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class, 'airline_id');
    }

    public function ref_model(): MorphTo
    {
        return $this->morphTo('ref_model', 'ref_model', 'ref_model_id');
    }
}
