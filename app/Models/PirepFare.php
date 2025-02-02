<?php

namespace App\Models;

use App\Contracts\Model;
use App\Models\Enums\FareType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int       id
 * @property string    pirep_id
 * @property int       fare_id
 * @property string    code
 * @property string    name
 * @property int       count
 * @property float     price
 * @property float $cost
 * @property int   $capacity
 * @property Pirep     pirep
 * @property Fare|null fare
 * @property FareType  type
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

    public static $rules = [
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
