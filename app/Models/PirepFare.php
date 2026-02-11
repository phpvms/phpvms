<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property string      $pirep_id
 * @property int|null    $fare_id
 * @property int|null    $count
 * @property string|null $code
 * @property string|null $name
 * @property float|null  $price
 * @property float|null  $cost
 * @property int|null    $capacity
 * @property int|null    $type
 * @property string|null $deleted_at
 * @property-read \App\Models\Fare|null $fare
 * @property-read \App\Models\Pirep|null $pirep
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFare newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFare newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFare query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFare whereCapacity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFare whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFare whereCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFare whereCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFare whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFare whereFareId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFare whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFare whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFare wherePirepId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFare wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFare whereType($value)
 *
 * @mixin \Eloquent
 */
class PirepFare extends Model
{
    public $table = 'pirep_fares';

    public $timestamps = false;

    protected $fillable = [
        'pirep_id',
        'fare_id',
        'code',
        'name',
        'count',
        'price',
        'cost',
        'capacity',
        'type',
    ];

    public static array $rules = [
        'count' => 'required',
    ];

    /**
     * Relationships
     */
    public function fare(): BelongsTo
    {
        return $this->belongsTo(Fare::class, 'fare_id');
    }

    public function pirep(): BelongsTo
    {
        return $this->belongsTo(Pirep::class, 'pirep_id');
    }

    protected function casts(): array
    {
        return [
            'count'    => 'integer',
            'price'    => 'float',
            'cost'     => 'float',
            'capacity' => 'integer',
            'type'     => 'integer',
        ];
    }
}
