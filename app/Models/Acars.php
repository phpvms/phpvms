<?php

namespace App\Models;

use App\Casts\DistanceCast;
use App\Casts\FuelCast;
use App\Contracts\Model;
use App\Enums\AcarsType;
use App\Enums\NavaidType;
use App\Traits\HashIdTrait;
use Database\Factories\AcarsFactory;
use App\Traits\HasNanoIds;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property string          $id
 * @property string          $pirep_id
 * @property AcarsType       $type
 * @property NavaidType|null $nav_type
 * @property int             $order
 * @property string|null     $name
 * @property string          $status
 * @property string|null     $log
 * @property float|null      $lat
 * @property float|null      $lon
 * @property mixed|null      $distance
 * @property int|null        $heading
 * @property float|null      $altitude_agl
 * @property float|null      $altitude_msl
 * @property float|null      $vs
 * @property int|null        $gs
 * @property int|null        $ias
 * @property int|null        $transponder
 * @property string|null     $autopilot
 * @property mixed|null      $fuel
 * @property float|null      $fuel_flow
 * @property string|null     $sim_time
 * @property Carbon|null     $created_at
 * @property Carbon|null     $updated_at
 * @property string|null     $source
 * @property float           $altitude
 * @property-read Pirep|null $pirep
 *
 * @method static AcarsFactory          factory($count = null, $state = [])
 * @method static Builder<static>|Acars flightPath()
 * @method static Builder<static>|Acars forPirep(string $pirepId)
 * @method static Builder<static>|Acars newModelQuery()
 * @method static Builder<static>|Acars newQuery()
 * @method static Builder<static>|Acars ofType(AcarsType|int $type)
 * @method static Builder<static>|Acars orderedByCreatedAt(string $direction = 'asc')
 * @method static Builder<static>|Acars orderedByOrder(string $direction = 'asc')
 * @method static Builder<static>|Acars orderedBySimTime(string $direction = 'asc')
 * @method static Builder<static>|Acars query()
 * @method static Builder<static>|Acars whereAltitudeAgl($value)
 * @method static Builder<static>|Acars whereAltitudeMsl($value)
 * @method static Builder<static>|Acars whereAutopilot($value)
 * @method static Builder<static>|Acars whereCreatedAt($value)
 * @method static Builder<static>|Acars whereDistance($value)
 * @method static Builder<static>|Acars whereFuel($value)
 * @method static Builder<static>|Acars whereFuelFlow($value)
 * @method static Builder<static>|Acars whereGs($value)
 * @method static Builder<static>|Acars whereHeading($value)
 * @method static Builder<static>|Acars whereIas($value)
 * @method static Builder<static>|Acars whereId($value)
 * @method static Builder<static>|Acars whereLat($value)
 * @method static Builder<static>|Acars whereLog($value)
 * @method static Builder<static>|Acars whereLon($value)
 * @method static Builder<static>|Acars whereName($value)
 * @method static Builder<static>|Acars whereNavType($value)
 * @method static Builder<static>|Acars whereOrder($value)
 * @method static Builder<static>|Acars wherePirepId($value)
 * @method static Builder<static>|Acars whereSimTime($value)
 * @method static Builder<static>|Acars whereSource($value)
 * @method static Builder<static>|Acars whereStatus($value)
 * @method static Builder<static>|Acars whereTransponder($value)
 * @method static Builder<static>|Acars whereType($value)
 * @method static Builder<static>|Acars whereUpdatedAt($value)
 * @method static Builder<static>|Acars whereVs($value)
 *
 * @mixin \Eloquent
 */
#[WithoutIncrementing]
class Acars extends Model
{
    use HasFactory;
    use HasNanoIds;

    public $table = 'acars';

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

    protected $appends = ['altitude'];

    #[Override]
    protected function casts(): array
    {
        return [
            'type'         => AcarsType::class,
            'order'        => 'integer',
            'nav_type'     => NavaidType::class,
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
    protected function ofType(Builder $query, AcarsType|int $type): void
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
