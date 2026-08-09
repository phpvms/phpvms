<?php

namespace App\Models;

use App\Casts\DistanceCast;
use App\Casts\FuelCast;
use App\Contracts\Model;
use Database\Factories\PirepPositionFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * A PIREP's last-known position, one row per flight. Its existence is what puts a
 * flight on the live map. `updated_at` moves on position batches only.
 *
 * @property string $pirep_id
 * @property int    $user_id
 * @property string $phase
 * @property float  $lat
 * @property float  $lon
 * @property int    $heading
 * @property mixed  $distance
 * @property float  $altitude_agl
 * @property float  $altitude_msl
 * @property float  $vs
 * @property int    $gs
 * @property int    $ias
 * @property int    $flight_time
 * @property mixed  $fuel_used
 * @property-read float $altitude
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
    /** @use HasFactory<PirepPositionFactory> */
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

    /** Units come from the casts, matching `acars` and `pireps`. */
    #[Override]
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            // `phase` is deliberately NOT cast to PirepPhase. It is a per-sample
            // reading passed through from the ACARS client, whose `phase` is an
            // open string vocabulary — a code this phpVMS predates must store
            // rather than throw on save. `pireps.status` keeps its cast: that is
            // a lifecycle column phpVMS owns and sets itself.
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

    /** Mirrors Acars::altitude, which the live map GeoJSON reads. */
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
