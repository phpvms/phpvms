<?php

declare(strict_types=1);

namespace App\Features\Tour\Models;

use App\Contracts\Model;
use App\Features\Tour\Enums\TourStatus;
use App\Models\Aircraft;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Models\Pirep;
use App\Models\User;
use App\Traits\HasNanoIds;
use Database\Factories\UserTourFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * One pilot's run through a tour bundle — see the create migration for why the
 * reference columns carry no foreign keys and why the text is snapshotted.
 *
 * The relations below are therefore all best-effort: `bundle()`, `aircraft()`,
 * `pirep()` and `flight()` can each resolve to null for a row that is still
 * perfectly readable from its own columns.
 *
 * @property string      $id
 * @property int         $user_id
 * @property int|null    $bundle_id
 * @property int|null    $aircraft_id
 * @property string|null $pirep_id       most recent leg PIREP, in any state
 * @property string|null $flight_id      the active leg's flight; null once completed
 * @property string      $name           snapshot of the bundle's
 * @property string|null $description    snapshot of the bundle's
 * @property TourStatus  $status
 * @property int         $legs_total
 * @property int         $legs_completed
 * @property array|null  $legs           [{flight_id, route_leg, pirep_id, filed_at}, ...]
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read FlightBundle|null $bundle
 * @property-read Aircraft|null $aircraft
 * @property-read Pirep|null $pirep
 * @property-read Flight|null $flight
 *
 * @method static UserTourFactory          factory($count = null, $state = [])
 * @method static Builder<static>|UserTour newModelQuery()
 * @method static Builder<static>|UserTour newQuery()
 * @method static Builder<static>|UserTour query()
 *
 * @mixin \Eloquent
 */
class UserTour extends Model
{
    /** @use HasFactory<UserTourFactory> */
    use HasFactory;

    use HasNanoIds;

    public $table = 'user_tours';

    protected $fillable = [
        'user_id',
        'bundle_id',
        'aircraft_id',
        'pirep_id',
        'flight_id',
        'name',
        'description',
        'status',
        'legs_total',
        'legs_completed',
        'legs',
        'started_at',
        'completed_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<FlightBundle, $this> */
    public function bundle(): BelongsTo
    {
        return $this->belongsTo(FlightBundle::class, 'bundle_id');
    }

    /** @return BelongsTo<Aircraft, $this> */
    public function aircraft(): BelongsTo
    {
        return $this->belongsTo(Aircraft::class, 'aircraft_id');
    }

    /** @return BelongsTo<Pirep, $this> */
    public function pirep(): BelongsTo
    {
        return $this->belongsTo(Pirep::class, 'pirep_id');
    }

    /** @return BelongsTo<Flight, $this> */
    public function flight(): BelongsTo
    {
        return $this->belongsTo(Flight::class, 'flight_id');
    }

    /**
     * The factory lives in Database\Factories with every other one. Without
     * this, Eloquent would look for it under the model's own namespace —
     * Database\Factories\Features\Tour\Models\UserTourFactory.
     */
    protected static function newFactory(): UserTourFactory
    {
        return UserTourFactory::new();
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'user_id'        => 'integer',
            'bundle_id'      => 'integer',
            'aircraft_id'    => 'integer',
            'status'         => TourStatus::class,
            'legs_total'     => 'integer',
            'legs_completed' => 'integer',
            'legs'           => 'array',
            'started_at'     => 'datetime',
            'completed_at'   => 'datetime',
        ];
    }
}
