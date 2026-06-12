<?php

namespace App\Models;

use App\Contracts\Model;
use App\Enums\FareType;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int           $id
 * @property string        $pirep_id
 * @property int|null      $fare_id
 * @property int|null      $count
 * @property string|null   $code
 * @property string|null   $name
 * @property float|null    $price
 * @property float|null    $cost
 * @property int|null      $capacity
 * @property FareType|null $type
 * @property string|null   $deleted_at
 * @property-read Fare|null $fare
 * @property-read Pirep|null $pirep
 *
 * @method static Builder<static>|PirepFare newModelQuery()
 * @method static Builder<static>|PirepFare newQuery()
 * @method static Builder<static>|PirepFare query()
 * @method static Builder<static>|PirepFare whereCapacity($value)
 * @method static Builder<static>|PirepFare whereCode($value)
 * @method static Builder<static>|PirepFare whereCost($value)
 * @method static Builder<static>|PirepFare whereCount($value)
 * @method static Builder<static>|PirepFare whereDeletedAt($value)
 * @method static Builder<static>|PirepFare whereFareId($value)
 * @method static Builder<static>|PirepFare whereId($value)
 * @method static Builder<static>|PirepFare whereName($value)
 * @method static Builder<static>|PirepFare wherePirepId($value)
 * @method static Builder<static>|PirepFare wherePrice($value)
 * @method static Builder<static>|PirepFare whereType($value)
 *
 * @mixin \Eloquent
 */
#[WithoutTimestamps]
class PirepFare extends Model
{
    public $table = 'pirep_fares';

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

    #[Override]
    protected function casts(): array
    {
        return [
            'count'    => 'integer',
            'price'    => 'float',
            'cost'     => 'float',
            'capacity' => 'integer',
            'type'     => FareType::class,
        ];
    }
}
