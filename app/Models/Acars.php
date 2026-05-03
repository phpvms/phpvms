<?php

namespace App\Models;

use App\Casts\DistanceCast;
use App\Casts\FuelCast;
use App\Contracts\Model;
use App\Models\Enums\AcarsType;
use App\Traits\HashIdTrait;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string      $id
 * @property string      $pirep_id
 * @property int         $type
 * @property int|null    $nav_type
 * @property int         $order
 * @property string|null $name
 * @property string      $status
 * @property string|null $log
 * @property float|null  $lat
 * @property float|null  $lon
 * @property mixed|null  $distance
 * @property int|null    $heading
 * @property float|null  $altitude_agl
 * @property float|null  $altitude_msl
 * @property float|null  $vs
 * @property int|null    $gs
 * @property int|null    $ias
 * @property int|null    $transponder
 * @property string|null $autopilot
 * @property mixed|null  $fuel
 * @property float|null  $fuel_flow
 * @property string|null $sim_time
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $source
 * @property mixed|null  $altitude
 * @property-read Pirep|null $pirep
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

    protected $appends = ['altitude'];

    protected function casts(): array
    {
        return [
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
    }

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

    #[Scope]
    protected function flightPath(Builder $query): void
    {
        $query->where('type', AcarsType::FLIGHT_PATH);
    }

    #[Scope]
    protected function forPirep(Builder $query, string $pirepId): void
    {
        $query->where('pirep_id', $pirepId);
    }

    #[Scope]
    protected function ofType(Builder $query, int $type): void
    {
        $query->where('type', $type);
    }

    #[Scope]
    protected function orderedByCreatedAt(Builder $query, string $direction = 'asc'): void
    {
        $query->orderBy('created_at', $direction === 'desc' ? 'desc' : 'asc');
    }

    #[Scope]
    protected function orderedByOrder(Builder $query, string $direction = 'asc'): void
    {
        $query->orderBy('order', $direction === 'desc' ? 'desc' : 'asc');
    }

    #[Scope]
    protected function orderedBySimTime(Builder $query, string $direction = 'asc'): void
    {
        $query->orderBy('sim_time', $direction === 'desc' ? 'desc' : 'asc');
    }

    /**
     * Relationships
     */
    public function pirep(): BelongsTo
    {
        return $this->belongsTo(Pirep::class, 'pirep_id');
    }
}
