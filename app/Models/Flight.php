<?php

namespace App\Models;

use App\Casts\DistanceCast;
use App\Contracts\Model;
use App\Models\Enums\Days;
use App\Observers\FlightObserver;
use App\Traits\HashIdTrait;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Kyslik\ColumnSortable\Sortable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string      $id
 * @property int         $airline_id
 * @property int         $flight_number
 * @property string|null $callsign
 * @property string|null $route_code
 * @property int|null    $route_leg
 * @property string      $dpt_airport_id
 * @property string      $arr_airport_id
 * @property string|null $alt_airport_id
 * @property string|null $dpt_time
 * @property string|null $arr_time
 * @property int|null    $level
 * @property mixed|null  $distance
 * @property int|null    $flight_time
 * @property string      $flight_type
 * @property float|null  $load_factor
 * @property float|null  $load_factor_variance
 * @property string|null $route
 * @property float|null  $pilot_pay
 * @property string|null $notes
 * @property int|null    $scheduled
 * @property int|null    $days
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property bool        $has_bid
 * @property bool        $active
 * @property bool        $visible
 * @property int|null    $event_id
 * @property int|null    $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Airline|null $airline
 * @property-read Airport|null $alt_airport
 * @property-read Airport|null $arr_airport
 * @property-read mixed $atc
 * @property-read Airport|null $dpt_airport
 * @property-read Event|null $event
 * @property-read Collection<int, Fare> $fares
 * @property-read int|null $fares_count
 * @property-read Collection<int, FlightFieldValue> $field_values
 * @property-read int|null $field_values_count
 * @property-read mixed $ident
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $owner
 * @property-read SimBrief|null $simbrief
 * @property-read Collection<int, Subfleet> $subfleets
 * @property-read int|null $subfleets_count
 * @property-read User|null $user
 *
 * @method static \Database\Factories\FlightFactory factory($count = null, $state = [])
 * @method static Builder<static>|Flight            active()
 * @method static Builder<static>|Flight            visible()
 * @method static Builder<static>|Flight            forAirline(int $airlineId)
 * @method static Builder<static>|Flight            fromAirport(string $icao)
 * @method static Builder<static>|Flight            toAirport(string $icao)
 * @method static Builder<static>|Flight            withFlightType(string $type)
 * @method static Builder<static>|Flight            distanceAtLeast(int $distance)
 * @method static Builder<static>|Flight            distanceAtMost(int $distance)
 * @method static Builder<static>|Flight            flightTimeAtLeast(int $minutes)
 * @method static Builder<static>|Flight            flightTimeAtMost(int $minutes)
 * @method static Builder<static>|Flight            withSubfleet(int $subfleetId)
 * @method static Builder<static>|Flight            forTypeRating(int $typeRatingId)
 * @method static Builder<static>|Flight            withIcaoType(string $icao)
 * @method static Builder<static>|Flight            newModelQuery()
 * @method static Builder<static>|Flight            newQuery()
 * @method static Builder<static>|Flight            onlyTrashed()
 * @method static Builder<static>|Flight            query()
 * @method static Builder<static>|Flight            sortable($defaultParameters = null)
 * @method static Builder<static>|Flight            whereActive($value)
 * @method static Builder<static>|Flight            whereAirlineId($value)
 * @method static Builder<static>|Flight            whereAltAirportId($value)
 * @method static Builder<static>|Flight            whereArrAirportId($value)
 * @method static Builder<static>|Flight            whereArrTime($value)
 * @method static Builder<static>|Flight            whereCallsign($value)
 * @method static Builder<static>|Flight            whereCreatedAt($value)
 * @method static Builder<static>|Flight            whereDays($value)
 * @method static Builder<static>|Flight            whereDeletedAt($value)
 * @method static Builder<static>|Flight            whereDistance($value)
 * @method static Builder<static>|Flight            whereDptAirportId($value)
 * @method static Builder<static>|Flight            whereDptTime($value)
 * @method static Builder<static>|Flight            whereEndDate($value)
 * @method static Builder<static>|Flight            whereEventId($value)
 * @method static Builder<static>|Flight            whereFlightNumber($value)
 * @method static Builder<static>|Flight            whereFlightTime($value)
 * @method static Builder<static>|Flight            whereFlightType($value)
 * @method static Builder<static>|Flight            whereHasBid($value)
 * @method static Builder<static>|Flight            whereId($value)
 * @method static Builder<static>|Flight            whereLevel($value)
 * @method static Builder<static>|Flight            whereLoadFactor($value)
 * @method static Builder<static>|Flight            whereLoadFactorVariance($value)
 * @method static Builder<static>|Flight            whereNotes($value)
 * @method static Builder<static>|Flight            whereOwnerId($value)
 * @method static Builder<static>|Flight            whereOwnerType($value)
 * @method static Builder<static>|Flight            wherePilotPay($value)
 * @method static Builder<static>|Flight            whereRoute($value)
 * @method static Builder<static>|Flight            whereRouteCode($value)
 * @method static Builder<static>|Flight            whereRouteLeg($value)
 * @method static Builder<static>|Flight            whereScheduled($value)
 * @method static Builder<static>|Flight            whereStartDate($value)
 * @method static Builder<static>|Flight            whereUpdatedAt($value)
 * @method static Builder<static>|Flight            whereUserId($value)
 * @method static Builder<static>|Flight            whereVisible($value)
 * @method static Builder<static>|Flight            withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Flight            withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[ObservedBy(FlightObserver::class)]
class Flight extends Model
{
    use HasFactory;
    use HashIdTrait;
    use LogsActivity;
    use SoftDeletes;
    use Sortable;

    public $table = 'flights';

    /** The form wants this */
    public $hours;

    public $minutes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'airline_id',
        'flight_number',
        'callsign',
        'route_code',
        'route_leg',
        'dpt_airport_id',
        'arr_airport_id',
        'alt_airport_id',
        'dpt_time',
        'arr_time',
        'days',
        'level',
        'distance',
        'flight_time',
        'flight_type',
        'load_factor',
        'load_factor_variance',
        'pilot_pay',
        'route',
        'notes',
        'start_date',
        'end_date',
        'has_bid',
        'active',
        'visible',
        'event_id',
        'user_id',
        'owner_type',
        'owner_id',
    ];

    public array $sortable = [
        'airline_id',
        'flight_number',
        'callsign',
        'route_code',
        'route_leg',
        'dpt_airport_id',
        'arr_airport_id',
        'alt_airport_id',
        'dpt_time',
        'arr_time',
        'distance',
        'notes',
        'flight_time',
        'flight_type',
        'event_id',
        'user_id',
    ];

    public array $sortableAs = [
        'subfleets_count',
        'fares_count',
    ];

    /**
     * Return all of the flights on any given day(s) of the week
     * Search using bitmasks
     *
     * @param  Days[]          $days List of the enumerated values
     * @return Builder<Flight>
     */
    public static function findByDays(array $days): Builder
    {
        /** @noinspection DynamicInvocationViaScopeResolutionInspection */
        $flights = self::where('active', true);
        foreach ($days as $day) {
            $flights = $flights->where('days', '&', $day);
        }

        return $flights;
    }

    /**
     * Get the flight ident, e.,g JBU1900/C.nn/L.yy
     */
    public function ident(): Attribute
    {
        return Attribute::make(
            get: function ($_, $attrs) {
                $flight_id = optional($this->airline)->code;
                $flight_id .= $this->flight_number;

                if (filled($this->route_code)) {
                    $flight_id .= '/C.'.$this->route_code;
                }

                if (filled($this->route_leg)) {
                    $flight_id .= '/L.'.$this->route_leg;
                }

                return $flight_id;
            }
        );
    }

    /**
     * Get the flight atc callsign, JBU1900 or JBU8FK
     */
    public function atc(): Attribute
    {
        return Attribute::make(
            get: function ($_, $attrs) {
                $flight_atc = optional($this->airline)->icao;

                if (!empty($this->callsign)) {
                    $flight_atc .= $this->callsign;
                } else {
                    $flight_atc .= $this->flight_number;
                }

                return $flight_atc;
            }
        );
    }

    public function on_day($day): bool
    {
        return ($this->days & $day) === $day;
    }

    /**
     * Return a custom field value
     */
    public function field($field_name): string
    {
        $field = $this->field_values->where('name', $field_name)->first();
        if ($field) {
            return $field['value'];
        }

        return '';
    }

    /**
     * Set the days parameter. If an array is passed, it's
     * AND'd together to create the mask value
     */
    public function days(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                if (\is_array($value)) {
                    $value = Days::getDaysMask($value);
                }

                return $value;
            }
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logExcept(['visible'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            // Bypass custom casts to log only internal DB changes (internal unit)
            ->useAttributeRawValues([
                'distance',
            ]);
    }

    /*
     * Relationships
     */
    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class, 'airline_id');
    }

    public function dpt_airport(): BelongsTo
    {
        return $this->belongsTo(Airport::class);
    }

    public function arr_airport(): BelongsTo
    {
        return $this->belongsTo(Airport::class);
    }

    public function alt_airport(): BelongsTo
    {
        return $this->belongsTo(Airport::class);
    }

    public function fares(): BelongsToMany
    {
        return $this->belongsToMany(Fare::class, 'flight_fare')->withPivot('price', 'cost', 'capacity');
    }

    public function field_values(): HasMany
    {
        return $this->hasMany(FlightFieldValue::class, 'flight_id', 'id');
    }

    public function simbrief(): BelongsTo
    {
        return $this->belongsTo(SimBrief::class, 'id', 'flight_id');
    }

    public function subfleets(): BelongsToMany
    {
        return $this->belongsToMany(Subfleet::class, 'flight_subfleet');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function owner(): MorphTo
    {
        return $this->morphTo('owner', 'owner_type', 'owner_id');
    }

    protected function casts(): array
    {
        return [
            'flight_number'        => 'integer',
            'days'                 => 'integer',
            'level'                => 'integer',
            'distance'             => DistanceCast::class,
            'flight_time'          => 'integer',
            'start_date'           => 'date',
            'end_date'             => 'date',
            'load_factor'          => 'double',
            'load_factor_variance' => 'double',
            'pilot_pay'            => 'float',
            'has_bid'              => 'boolean',
            'route_leg'            => 'integer',
            'active'               => 'boolean',
            'visible'              => 'boolean',
            'event_id'             => 'integer',
            'user_id'              => 'integer',
        ];
    }

    /*
     * Query scopes
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    #[Scope]
    protected function visible(Builder $query): Builder
    {
        return $query->where('visible', true);
    }

    #[Scope]
    protected function forAirline(Builder $query, int $airlineId): Builder
    {
        return $query->where('airline_id', $airlineId);
    }

    #[Scope]
    protected function fromAirport(Builder $query, string $icao): Builder
    {
        return $query->where('dpt_airport_id', strtoupper($icao));
    }

    #[Scope]
    protected function toAirport(Builder $query, string $icao): Builder
    {
        return $query->where('arr_airport_id', strtoupper($icao));
    }

    #[Scope]
    protected function withFlightType(Builder $query, string $type): Builder
    {
        return $query->where('flight_type', $type);
    }

    #[Scope]
    protected function distanceAtLeast(Builder $query, int $distance): Builder
    {
        return $query->where('distance', '>=', $distance);
    }

    #[Scope]
    protected function distanceAtMost(Builder $query, int $distance): Builder
    {
        return $query->where('distance', '<=', $distance);
    }

    #[Scope]
    protected function flightTimeAtLeast(Builder $query, int $minutes): Builder
    {
        return $query->where('flight_time', '>=', $minutes);
    }

    #[Scope]
    protected function flightTimeAtMost(Builder $query, int $minutes): Builder
    {
        return $query->where('flight_time', '<=', $minutes);
    }

    #[Scope]
    protected function withSubfleet(Builder $query, int $subfleetId): Builder
    {
        return $query->whereHas(
            'subfleets',
            fn (Builder $sq) => $sq->where('subfleets.id', $subfleetId)
        );
    }

    /**
     * Filter to flights whose subfleets are part of the given type rating.
     */
    #[Scope]
    protected function forTypeRating(Builder $query, int $typeRatingId): Builder
    {
        $subfleetIds = Typerating::with('subfleets')
            ->where('id', $typeRatingId)
            ->first()
            ?->subfleets
            ->pluck('id')
            ->all() ?? [];

        return $query->whereHas(
            'subfleets',
            fn (Builder $sq) => $sq->whereIn('subfleets.id', $subfleetIds)
        );
    }

    /**
     * Filter to flights flown by aircraft of a given ICAO type code.
     */
    #[Scope]
    protected function withIcaoType(Builder $query, string $icao): Builder
    {
        $subfleetIds = Aircraft::where('icao', strtoupper(trim($icao)))
            ->groupBy('subfleet_id')
            ->pluck('subfleet_id')
            ->all();

        return $query->whereHas(
            'subfleets',
            fn (Builder $sq) => $sq->whereIn('subfleets.id', $subfleetIds)
        );
    }
}
