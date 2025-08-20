<?php

namespace App\Models;

use App\Contracts\Model;
use App\Events\PirepStateChange;
use App\Events\PirepStatusChange;
use App\Models\Casts\CarbonCast;
use App\Models\Casts\DistanceCast;
use App\Models\Casts\FuelCast;
use App\Models\Enums\AcarsType;
use App\Models\Enums\PirepFieldSource;
use App\Models\Enums\PirepState;
use App\Models\Traits\HashIdTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Kleemans\AttributeEvents;
use Kyslik\ColumnSortable\Sortable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string                          $id
 * @property int                             $user_id
 * @property int                             $airline_id
 * @property int|null                        $aircraft_id
 * @property int|null                        $event_id
 * @property string|null                     $flight_id
 * @property string|null                     $flight_number
 * @property string|null                     $route_code
 * @property string|null                     $route_leg
 * @property string                          $flight_type
 * @property string                          $dpt_airport_id
 * @property string                          $arr_airport_id
 * @property string|null                     $alt_airport_id
 * @property int|null                        $level
 * @property mixed|null                      $distance
 * @property mixed|null                      $planned_distance
 * @property int|null                        $flight_time
 * @property int|null                        $planned_flight_time
 * @property float|null                      $zfw
 * @property mixed|null                      $block_fuel
 * @property mixed|null                      $fuel_used
 * @property float|null                      $landing_rate
 * @property int|null                        $score
 * @property string|null                     $route
 * @property string|null                     $notes
 * @property int|null                        $source
 * @property string|null                     $source_name
 * @property int                             $state
 * @property string                          $status
 * @property mixed|null                      $submitted_at
 * @property mixed|null                      $block_off_time
 * @property mixed|null                      $block_on_time
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Acars> $acars
 * @property-read int|null $acars_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Acars> $acars_logs
 * @property-read int|null $acars_logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Acars> $acars_route
 * @property-read int|null $acars_route_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\Aircraft|null $aircraft
 * @property-read \App\Models\Airline|null $airline
 * @property-read \App\Models\Airport|null $alt_airport
 * @property-read \App\Models\Airport|null $arr_airport
 * @property-read mixed $cancelled
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PirepComment> $comments
 * @property-read int|null $comments_count
 * @property-read \App\Models\Airport|null $dpt_airport
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PirepFare> $fares
 * @property-read int|null $fares_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PirepFieldValue> $field_values
 * @property-read int|null $field_values_count
 * @property-read mixed $fields
 * @property-read \App\Models\Flight|null $flight
 * @property-read mixed $ident
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \App\Models\Acars|null $position
 * @property-read mixed $progress_percent
 * @property-read mixed $read_only
 * @property-read \App\Models\SimBrief|null $simbrief
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\JournalTransaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read \App\Models\User|null $user
 *
 * @method static \Database\Factories\PirepFactory                    factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep sortable($defaultParameters = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereAircraftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereAirlineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereAltAirportId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereArrAirportId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereBlockFuel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereBlockOffTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereBlockOnTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereDistance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereDptAirportId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereFlightId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereFlightNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereFlightTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereFlightType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereFuelUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereLandingRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep wherePlannedDistance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep wherePlannedFlightTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereRoute($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereRouteCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereRouteLeg($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereSourceName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereSubmittedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep whereZfw($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pirep withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Pirep extends Model
{
    use AttributeEvents;
    use HasFactory;
    use HashIdTrait;
    use LogsActivity;
    use Notifiable;
    use SoftDeletes;
    use Sortable;

    public $table = 'pireps';

    protected $keyType = 'string';

    public $incrementing = false;

    /** The form wants this */
    public $hours;

    public $minutes;

    protected $fillable = [
        'id',
        'user_id',
        'airline_id',
        'aircraft_id',
        'event_id',
        'flight_number',
        'route_code',
        'route_leg',
        'flight_id',
        'dpt_airport_id',
        'arr_airport_id',
        'alt_airport_id',
        'level',
        'distance',
        'planned_distance',
        'block_time',
        'flight_time',
        'planned_flight_time',
        'zfw',
        'block_fuel',
        'fuel_used',
        'landing_rate',
        'route',
        'notes',
        'score',
        'source',
        'source_name',
        'flight_type',
        'state',
        'status',
        'block_off_time',
        'block_on_time',
        'submitted_at',
        'created_at',
        'updated_at',
    ];

    public static array $rules = [
        'airline_id'     => 'required|exists:airlines,id',
        'aircraft_id'    => 'required|exists:aircraft,id',
        'event_id'       => 'nullable|numeric',
        'flight_number'  => 'required',
        'dpt_airport_id' => 'required',
        'arr_airport_id' => 'required',
        'block_fuel'     => 'nullable|numeric',
        'fuel_used'      => 'nullable|numeric',
        'level'          => 'nullable|numeric',
        'notes'          => 'nullable',
        'route'          => 'nullable',
    ];

    public $sortable = [
        'user_id',
        'airline_id',
        'aircraft_id',
        'event_id',
        'flight_number',
        'route_code',
        'route_leg',
        'flight_id',
        'dpt_airport_id',
        'arr_airport_id',
        'alt_airport_id',
        'distance',
        'flight_time',
        'fuel_used',
        'landing_rate',
        'score',
        'flight_type',
        'source',
        'state',
        'status',
        'submitted_at',
        'created_at',
    ];

    /**
     * Auto-dispatch events for lifecycle state changes
     */
    protected $dispatchesEvents = [
        'status:*' => PirepStatusChange::class,
        'state:*'  => PirepStateChange::class,
    ];

    /*
     * If a PIREP is in these states, then it can't be changed.
     */
    public static $read_only_states = [
        PirepState::ACCEPTED,
        PirepState::REJECTED,
        PirepState::CANCELLED,
    ];

    /*
     * If a PIREP is in one of these states, it can't be cancelled
     */
    public static $cancel_states = [
        PirepState::ACCEPTED,
        PirepState::REJECTED,
        PirepState::CANCELLED,
        PirepState::DELETED,
    ];

    /**
     * Create a new PIREP model from a given flight. Pre-populates the fields
     */
    public static function fromFlight(Flight $flight): self
    {
        return new self([
            'flight_id'      => $flight->id,
            'airline_id'     => $flight->airline_id,
            'event_id'       => $flight->event_id,
            'flight_number'  => $flight->flight_number,
            'route_code'     => $flight->route_code,
            'route_leg'      => $flight->route_leg,
            'dpt_airport_id' => $flight->dpt_airport_id,
            'arr_airport_id' => $flight->arr_airport_id,
            'route'          => $flight->route,
            'level'          => $flight->level,
        ]);
    }

    /**
     * Create a new PIREP from a SimBrief instance
     */
    public static function fromSimBrief(SimBrief $simbrief): self
    {
        return new self([
            'flight_id'      => $simbrief->flight->id,
            'airline_id'     => $simbrief->flight->airline_id,
            'event_id'       => $simbrief->flight->event_id,
            'flight_number'  => $simbrief->flight->flight_number,
            'route_code'     => $simbrief->flight->route_code,
            'route_leg'      => $simbrief->flight->route_leg,
            'dpt_airport_id' => $simbrief->flight->dpt_airport_id,
            'arr_airport_id' => $simbrief->flight->arr_airport_id,
            'route'          => $simbrief->xml->getRouteString(),
            'level'          => $simbrief->xml->getFlightLevel(),
        ]);
    }

    /**
     * Get the flight ident, e.,g JBU1900/C.nn/L.yy
     */
    public function ident(): Attribute
    {
        return Attribute::make(get: function ($value, $attrs) {
            $flight_id = optional($this->airline)->code;
            $flight_id .= $this->flight_number;

            if (filled($this->route_code)) {
                $flight_id .= '/C.'.$this->route_code;
            }

            if (filled($this->route_leg)) {
                $flight_id .= '/L.'.$this->route_leg;
            }

            return $flight_id;
        });
    }

    /**
     * Return if this PIREP can be edited or not
     */
    public function readOnly(): Attribute
    {
        return Attribute::make(get: fn ($_, $attrs) => \in_array(
            $this->state,
            static::$read_only_states,
            true
        ));
    }

    /**
     * Return the flight progress in a percent.
     */
    public function progressPercent(): Attribute
    {
        return Attribute::make(get: function ($_, $attrs) {
            $distance = $attrs['distance'];

            $upper_bound = $distance;
            if (!empty($attrs['planned_distance']) && $attrs['planned_distance'] > 0) {
                $upper_bound = $attrs['planned_distance'];
            }

            $upper_bound = empty($upper_bound) ? 1 : $upper_bound;
            $distance = empty($distance) ? $upper_bound : $distance;

            return round(($distance / $upper_bound) * 100);
        });
    }

    /**
     * Get the pirep_fields and then the pirep_field_values and
     * merge them together. If a field value doesn't exist then add in a fake one
     */
    public function fields(): Attribute
    {
        return Attribute::make(get: function ($_, $attrs) {
            $custom_fields = PirepField::whereIn('pirep_source', [$this->source, PirepFieldSource::BOTH])->get();
            $field_values = PirepFieldValue::where('pirep_id', $this->id)->orderBy(
                'created_at',
                'asc'
            )->get();

            // Merge the field values into $fields
            foreach ($custom_fields as $field) {
                $has_value = $field_values->firstWhere('slug', $field->slug);
                if (!$has_value) {
                    $field_values->push(
                        new PirepFieldValue([
                            'pirep_id' => $this->id,
                            'name'     => $field->name,
                            'slug'     => $field->slug,
                            'value'    => '',
                            'source'   => PirepFieldSource::MANUAL,
                        ])
                    );
                }
            }

            return $field_values;
        });
    }

    /**
     * Do some cleanup on the route
     */
    public function route(): Attribute
    {
        return Attribute::make(set: fn ($route) => strtoupper(trim($route)));
    }

    /**
     * Return if this is cancelled or not
     */
    public function cancelled(): Attribute
    {
        return Attribute::make(get: fn ($_, $attrs) => $this->state === PirepState::CANCELLED);
    }

    /**
     * Check if this PIREP is allowed to be updated
     */
    public function allowedUpdates(): bool
    {
        return !$this->read_only;
    }

    /**
     * Return a custom field value
     */
    public function field($field_name): string
    {
        $field = $this->fields->where('name', $field_name)->first();
        if ($field) {
            return $field['value'];
        }

        return '';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logExcept(['created_at', 'updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Relationships
     */
    public function acars(): HasMany
    {
        return $this->hasMany(Acars::class, 'pirep_id')->where(
            'type',
            AcarsType::FLIGHT_PATH
        )->orderBy('created_at', 'asc')->orderBy('sim_time', 'asc');
    }

    public function acars_logs(): HasMany
    {
        return $this->hasMany(Acars::class, 'pirep_id')->where('type', AcarsType::LOG)->orderBy(
            'created_at',
            'desc'
        )->orderBy('sim_time', 'asc');
    }

    public function acars_route(): HasMany
    {
        return $this->hasMany(Acars::class, 'pirep_id')->where('type', AcarsType::ROUTE)->orderBy(
            'order',
            'asc'
        );
    }

    public function aircraft(): BelongsTo
    {
        return $this->belongsTo(Aircraft::class, 'aircraft_id');
    }

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class, 'airline_id');
    }

    public function flight(): BelongsTo
    {
        return $this->belongsTo(Flight::class, 'flight_id');
    }

    public function arr_airport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'arr_airport_id')->withDefault(function ($model) {
            if (!empty($this->attributes['arr_airport_id'])) {
                $model->id = $this->attributes['arr_airport_id'];
                $model->icao = $this->attributes['arr_airport_id'];
                $model->name = $this->attributes['arr_airport_id'];
            }

            return $model;
        });
    }

    public function alt_airport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'alt_airport_id');
    }

    public function dpt_airport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'dpt_airport_id')->withDefault(function ($model) {
            if (!empty($this->attributes['dpt_airport_id'])) {
                $model->id = $this->attributes['dpt_airport_id'];
                $model->icao = $this->attributes['dpt_airport_id'];
                $model->name = $this->attributes['dpt_airport_id'];
            }

            return $model;
        });
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PirepComment::class, 'pirep_id')->orderBy('created_at', 'desc');
    }

    public function fares(): HasMany
    {
        return $this->hasMany(PirepFare::class, 'pirep_id');
    }

    public function field_values(): HasMany
    {
        return $this->hasMany(PirepFieldValue::class, 'pirep_id');
    }

    public function pilot()
    {
        return $this->user();
    }

    /**
     * Relationship that holds the current position, but limits the ACARS
     *  relationship to only one row (the latest), to prevent an N+! problem
     */
    public function position(): HasOne
    {
        return $this->hasOne(Acars::class, 'pirep_id')->where(
            'type',
            AcarsType::FLIGHT_PATH
        )->latest();
    }

    public function simbrief(): BelongsTo
    {
        return $this->belongsTo(SimBrief::class, 'id', 'pirep_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(JournalTransaction::class, 'ref_model_id')->where(
            'ref_model',
            __CLASS__
        )->orderBy('credit', 'desc')->orderBy('debit', 'desc');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'user_id'             => 'integer',
            'airline_id'          => 'integer',
            'aircraft_id'         => 'integer',
            'event_id'            => 'integer',
            'level'               => 'integer',
            'distance'            => DistanceCast::class,
            'planned_distance'    => DistanceCast::class,
            'block_time'          => 'integer',
            'block_off_time'      => CarbonCast::class,
            'block_on_time'       => CarbonCast::class,
            'flight_time'         => 'integer',
            'planned_flight_time' => 'integer',
            'zfw'                 => 'float',
            'block_fuel'          => FuelCast::class,
            'fuel_used'           => FuelCast::class,
            'landing_rate'        => 'float',
            'score'               => 'integer',
            'source'              => 'integer',
            'state'               => 'integer',
            'submitted_at'        => CarbonCast::class,
        ];
    }
}
