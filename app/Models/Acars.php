<?php

namespace App\Models;

use App\Contracts\Model;
use App\Models\Casts\DistanceCast;
use App\Models\Casts\FuelCast;
use App\Models\Traits\HashIdTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string id
 * @property string pirep_id
 * @property int    type
 * @property string name
 * @property float  lat
 * @property float  lon
 * @property float  altitude_agl
 * @property float  altitude_msl
 * @property int    gs
 * @property int    ias
 * @property int    heading
 * @property int    order
 * @property int    nav_type
 */
class Acars extends Model
{
    use HasFactory;
    use HashIdTrait;

    public $table = 'acars';

    protected $keyType = 'string';

    public $fillable = [
        'id',
        'pirep_id',
        'type',
        'nav_type',
        'order',
        'name',
        'status',
        'log',
        'lat',
        'lon',
        'distance',
        'heading',
        'altitude',
        'altitude_agl',
        'altitude_msl',
        'vs',
        'gs',
        'ias',
        'transponder',
        'autopilot',
        'fuel_flow',
        'sim_time',
        'source',
        'created_at',
        'updated_at',
    ];

    public $incrementing = false;

    public $casts = [
        'type'         => 'integer',
        'order'        => 'integer',
        'nav_type'     => 'integer',
        'lat'          => 'float',
        'lon'          => 'float',
        'distance'     => DistanceCast::class,
        'heading'      => 'integer',
        'altitude_agl' => 'float',
        'altitude_msl' => 'float',
        'vs'           => 'float',
        'gs'           => 'integer',
        'ias'          => 'integer',
        'transponder'  => 'integer',
        'fuel'         => FuelCast::class,
        'fuel_flow'    => 'float',
    ];

    public static array $rules = [
        'pirep_id' => 'required',
    ];

    protected $appends = ['altitude'];

    /**
     * This keeps things backwards compatible with previous versions
     * which send in altitude only
     */
    public function altitude(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $_, array $attrs): float => (float) $attrs['altitude_msl'],
            set: function (mixed $value): array {
                $ret = [];
                if (!array_key_exists('altitude_agl', $this->attributes)) {
                    $ret['altitude_agl'] = (float) $value;
                }

                if (!array_key_exists('altitude_msl', $this->attributes)) {
                    $ret['altitude_msl'] = (float) $value;
                }

                return $ret;
            }
        );
    }

    /**
     * Relationships
     */
    public function pirep(): BelongsTo
    {
        return $this->belongsTo(Pirep::class, 'pirep_id');
    }
}
