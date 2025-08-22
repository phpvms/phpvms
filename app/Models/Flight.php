<?php

namespace App\Models;

use App\Contracts\Model;
use App\Models\Casts\DistanceCast;
use App\Models\Enums\Days;
use App\Models\Traits\HashIdTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kyslik\ColumnSortable\Sortable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string                          $id
 * @property int                             $airline_id
 * @property int                             $flight_number
 * @property string|null                     $callsign
 * @property string|null                     $route_code
 * @property int|null                        $route_leg
 * @property string                          $dpt_airport_id
 * @property string                          $arr_airport_id
 * @property string|null                     $alt_airport_id
 * @property string|null                     $dpt_time
 * @property string|null                     $arr_time
 * @property int|null                        $level
 * @property mixed|null                      $distance
 * @property int|null                        $flight_time
 * @property string                          $flight_type
 * @property float|null                      $load_factor
 * @property float|null                      $load_factor_variance
 * @property string|null                     $route
 * @property float|null                      $pilot_pay
 * @property string|null                     $notes
 * @property int|null                        $scheduled
 * @property int|null                        $days
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property bool                            $has_bid
 * @property bool                            $active
 * @property bool                            $visible
 * @property int|null                        $event_id
 * @property int|null                        $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null                     $owner_type
 * @property string|null                     $owner_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\Airline|null $airline
 * @property-read \App\Models\Airport|null $alt_airport
 * @property-read \App\Models\Airport|null $arr_airport
 * @property-read mixed $atc
 * @property-read \App\Models\Airport|null $dpt_airport
 * @property-read \App\Models\Event|null $event
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fare> $fares
 * @property-read int|null $fares_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FlightFieldValue> $field_values
 * @property-read int|null $field_values_count
 * @property-read mixed $ident
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $owner
 * @property-read \App\Models\SimBrief|null $simbrief
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Subfleet> $subfleets
 * @property-read int|null $subfleets_count
 * @property-read \App\Models\User|null $user
 *
 * @method static \Database\Factories\FlightFactory factory($count = null, $state = [])
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

    public static array $rules = [
        'airline_id'           => 'required|exists:airlines,id',
        'flight_number'        => 'required',
        'callsign'             => 'string|max:4|nullable',
        'route_code'           => 'nullable',
        'route_leg'            => 'nullable',
        'dpt_airport_id'       => 'required|exists:airports,id',
        'arr_airport_id'       => 'required|exists:airports,id',
        'load_factor'          => 'nullable|numeric',
        'load_factor_variance' => 'nullable|numeric',
        'level'                => 'nullable',
        'event_id'             => 'nullable|numeric',
        'user_id'              => 'nullable|numeric',
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
            ->dontSubmitEmptyLogs();
    }

    /*
     * Relationships
     */
    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class, 'airline_id');
    }

    public function dpt_airport(): HasOne
    {
        return $this->hasOne(Airport::class, 'id', 'dpt_airport_id');
    }

    public function arr_airport(): HasOne
    {
        return $this->hasOne(Airport::class, 'id', 'arr_airport_id');
    }

    public function alt_airport(): HasOne
    {
        return $this->hasOne(Airport::class, 'id', 'alt_airport_id');
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
        return $this->belongsTo(User::class, 'id', 'user_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'id', 'event_id');
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
}
