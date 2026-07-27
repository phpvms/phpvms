<?php

namespace App\Models;

use App\Casts\DistanceCast;
use App\Casts\FuelCast;
use App\Contracts\Model;
use App\Enums\PirepPhase;
use Database\Factories\PirepPositionFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * A PIREP's last-known position — one row per flight, overwritten by each
 * position batch, and the only thing the live map reads.
 *
 * The row's existence is what puts a flight on the map. It is created at
 * prefile, maintained by the ACARS batch endpoint, and removed by
 * `PirepPositionExpiration` or by cancelling the PIREP. Nothing else writes it,
 * and no read path filters it.
 *
 * `updated_at` is the liveness clock: it moves on position batches and on
 * nothing else, so an administrator editing a PIREP cannot make a dead flight
 * look alive.
 *
 * This is not a record of account. It is reconstructible from the latest `acars`
 * row for the same PIREP, so a divergence between the two heals on the next
 * position batch and needs no reconciliation.
 *
 * @property string      $pirep_id
 * @property int         $user_id
 * @property PirepPhase  $phase
 * @property float       $lat
 * @property float       $lon
 * @property int         $heading
 * @property mixed       $distance
 * @property float       $altitude_agl
 * @property float       $altitude_msl
 * @property float       $vs
 * @property int         $gs
 * @property int         $ias
 * @property int         $flight_time
 * @property mixed       $fuel_used
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Pirep|null $pirep
 * @property-read User|null  $user
 *
 * @method static PirepPositionFactory          factory($count = null, $state = [])
 * @method static Builder<static>|PirepPosition newModelQuery()
 * @method static Builder<static>|PirepPosition newQuery()
 * @method static Builder<static>|PirepPosition query()
 */
#[WithoutIncrementing]
class PirepPosition extends Model
{
    use HasFactory;

    public $table = 'pirep_positions';

    protected $appends = ['altitude'];

    protected $primaryKey = 'pirep_id';

    protected $keyType = 'string';

    public $fillable = [
        'pirep_id',
        'user_id',
        'phase',
        'lat',
        'lon',
        'heading',
        'distance',
        'altitude_agl',
        'altitude_msl',
        'vs',
        'gs',
        'ias',
        'flight_time',
        'fuel_used',
    ];

    /**
     * Units live in the casts, not the column names, which is what makes the
     * operator's configured display units apply here exactly as they do to
     * `acars` and `pireps`.
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'user_id'      => 'integer',
            'phase'        => PirepPhase::class,
            'lat'          => 'float',
            'lon'          => 'float',
            'heading'      => 'integer',
            'distance'     => DistanceCast::class,
            'altitude_agl' => 'float',
            'altitude_msl' => 'float',
            'vs'           => 'float',
            'gs'           => 'integer',
            'ias'          => 'integer',
            'flight_time'  => 'integer',
            'fuel_used'    => FuelCast::class,
        ];
    }

    /**
     * Mirrors `Acars`.`altitude`, which is what the live map's GeoJSON reads for
     * a point's altitude.
     */
    protected function altitude(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $_, array $attrs): float => (float) $attrs['altitude_msl'],
        );
    }

    public function pirep(): BelongsTo
    {
        return $this->belongsTo(Pirep::class, 'pirep_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
