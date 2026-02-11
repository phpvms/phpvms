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
 * @property string                          $id
 * @property string                          $pirep_id
 * @property int                             $type
 * @property int|null                        $nav_type
 * @property int                             $order
 * @property string|null                     $name
 * @property string                          $status
 * @property string|null                     $log
 * @property float|null                      $lat
 * @property float|null                      $lon
 * @property mixed|null                      $distance
 * @property int|null                        $heading
 * @property float|null                      $altitude_agl
 * @property float|null                      $altitude_msl
 * @property float|null                      $vs
 * @property int|null                        $gs
 * @property int|null                        $ias
 * @property int|null                        $transponder
 * @property string|null                     $autopilot
 * @property mixed|null                      $fuel
 * @property float|null                      $fuel_flow
 * @property string|null                     $sim_time
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null                     $source
 * @property mixed|null                      $altitude
 * @property-read \App\Models\Pirep|null $pirep
 *
 * @method static \Database\Factories\AcarsFactory                    factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereAltitudeAgl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereAltitudeMsl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereAutopilot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereDistance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereFuel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereFuelFlow($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereGs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereHeading($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereIas($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereLat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereLog($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereLon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereNavType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars wherePirepId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereSimTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereTransponder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Acars whereVs($value)
 *
 * @mixin \Eloquent
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
    protected function altitude(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $_, array $attrs) => (float) $attrs['altitude_msl'],
            set: function (mixed $value) {
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
