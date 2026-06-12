<?php

namespace App\Models;

use App\Casts\DistanceCast;
use App\Contracts\Model;
use App\Enums\FlightType;
use App\Support\Days;
use App\Traits\HashIdTrait;
use BackedEnum;
use Database\Factories\FlightFactory;
use Deprecated;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
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
use Override;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Stringable;
use UnitEnum;

/**
 * @property string            $id
 * @property int|null          $bundle_id
 * @property FlightBundle|null $bundle
 * @property int               $airline_id
 * @property int               $flight_number
 * @property string|null       $callsign
 * @property string|null       $route_code
 * @property int|null          $route_leg
 * @property string            $dpt_airport_id
 * @property string            $arr_airport_id
 * @property string|null       $alt_airport_id
 * @property string|null       $dpt_time
 * @property string|null       $arr_time
 * @property Carbon|null       $departure_time
 * @property Carbon|null       $arrival_time
 * @property int|null          $level
 * @property mixed|null        $distance
 * @property int|null          $flight_time
 * @property FlightType        $flight_type
 * @property float|null        $load_factor
 * @property float|null        $load_factor_variance
 * @property string|null       $route
 * @property float|null        $pilot_pay
 * @property string|null       $notes
 * @property int|null          $scheduled
 * @property int|null          $days
 * @property Carbon|null       $start_date
 * @property Carbon|null       $end_date
 * @property bool              $has_bid
 * @property bool              $enabled
 * @property bool              $visible
 * @property int|null          $event_id
 * @property int|null          $user_id
 * @property Carbon|null       $created_at
 * @property Carbon|null       $updated_at
 * @property Carbon|null       $deleted_at
 * @property string|null       $owner_type
 * @property string|null       $owner_id
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Airline|null $airline
 * @property-read Airport|null $alt_airport
 * @property-read Airport|null $arr_airport
 * @property-read string $atc
 * @property-read Airport|null $dpt_airport
 * @property-read Event|null $event
 * @property-read Collection<int, Fare> $fares
 * @property-read int|null $fares_count
 * @property-read Collection<int, FlightFieldValue> $field_values
 * @property-read int|null $field_values_count
 * @property-read string $ident
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $owner
 * @property-read SimBrief|null $simbrief
 * @property-read Collection<int, Subfleet> $subfleets
 * @property-read int|null $subfleets_count
 * @property-read User|null $user
 *
 * @method static Builder<static>|Flight active()
 * @method static Builder<static>|Flight distanceAtLeast(int $distance)
 * @method static Builder<static>|Flight distanceAtMost(int $distance)
 * @method static FlightFactory          factory($count = null, $state = [])
 * @method static Builder<static>|Flight flightTimeAtLeast(int $minutes)
 * @method static Builder<static>|Flight flightTimeAtMost(int $minutes)
 * @method static Builder<static>|Flight forAirline(int $airlineId)
 * @method static Builder<static>|Flight forTypeRating(int $typeRatingId)
 * @method static Builder<static>|Flight fromAirport(string $icao)
 * @method static Builder<static>|Flight newModelQuery()
 * @method static Builder<static>|Flight newQuery()
 * @method static Builder<static>|Flight onlyTrashed()
 * @method static Builder<static>|Flight query()
 * @method static Builder<static>|Flight sortable($defaultParameters = null)
 * @method static Builder<static>|Flight toAirport(string $icao)
 * @method static Builder<static>|Flight visible()
 * @method static Builder<static>|Flight whereEnabled($value)
 * @method static Builder<static>|Flight whereAirlineId($value)
 * @method static Builder<static>|Flight whereAltAirportId($value)
 * @method static Builder<static>|Flight whereArrAirportId($value)
 * @method static Builder<static>|Flight whereArrTime($value)
 * @method static Builder<static>|Flight whereCallsign($value)
 * @method static Builder<static>|Flight whereCreatedAt($value)
 * @method static Builder<static>|Flight whereDays($value)
 * @method static Builder<static>|Flight whereDeletedAt($value)
 * @method static Builder<static>|Flight whereDistance($value)
 * @method static Builder<static>|Flight whereDptAirportId($value)
 * @method static Builder<static>|Flight whereDptTime($value)
 * @method static Builder<static>|Flight whereEndDate($value)
 * @method static Builder<static>|Flight whereEventId($value)
 * @method static Builder<static>|Flight whereFlightNumber($value)
 * @method static Builder<static>|Flight whereFlightTime($value)
 * @method static Builder<static>|Flight whereFlightType($value)
 * @method static Builder<static>|Flight whereHasBid($value)
 * @method static Builder<static>|Flight whereId($value)
 * @method static Builder<static>|Flight whereLevel($value)
 * @method static Builder<static>|Flight whereLoadFactor($value)
 * @method static Builder<static>|Flight whereLoadFactorVariance($value)
 * @method static Builder<static>|Flight whereNotes($value)
 * @method static Builder<static>|Flight whereOwnerId($value)
 * @method static Builder<static>|Flight whereOwnerType($value)
 * @method static Builder<static>|Flight wherePilotPay($value)
 * @method static Builder<static>|Flight whereRoute($value)
 * @method static Builder<static>|Flight whereRouteCode($value)
 * @method static Builder<static>|Flight whereRouteLeg($value)
 * @method static Builder<static>|Flight whereScheduled($value)
 * @method static Builder<static>|Flight whereStartDate($value)
 * @method static Builder<static>|Flight whereUpdatedAt($value)
 * @method static Builder<static>|Flight whereUserId($value)
 * @method static Builder<static>|Flight whereVisible($value)
 * @method static Builder<static>|Flight withFlightType(string $type)
 * @method static Builder<static>|Flight withIcaoType(string $icao)
 * @method static Builder<static>|Flight withSubfleet(int $subfleetId)
 * @method static Builder<static>|Flight withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Flight withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[WithoutIncrementing]
class Flight extends Model
{
    use HasFactory;
    use HashIdTrait;
    use LogsActivity;
    use SoftDeletes;
    use Sortable;

    public $table = 'flights';

    public $hours;

    public $minutes;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'bundle_id',
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
        'departure_time',
        'arrival_time',
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
        'enabled',
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

    #[Override]
    protected function casts(): array
    {
        return [
            'flight_number'  => 'integer',
            'days'           => 'integer',
            'level'          => 'integer',
            'distance'       => DistanceCast::class,
            'flight_time'    => 'integer',
            'flight_type'    => FlightType::class,
            'departure_time' => 'datetime:H:i:s',
            'arrival_time'   => 'datetime:H:i:s',
            'start_date'     => 'datetime',
            'end_date'       => 'datetime',
            // `load_factor` and `load_factor_variance` double casts are handled by
            // their Attribute mutators so blank string inputs canonicalize to NULL
            // rather than causing MySQL strict-mode errors on DECIMAL columns.
            'pilot_pay' => 'float',
            'has_bid'   => 'boolean',
            // `route_leg` int cast is handled by the routeLeg() Attribute
            // mutator so empty / '0' inputs canonicalize to NULL rather than 0.
            'enabled'   => 'boolean',
            'visible'   => 'boolean',
            'event_id'  => 'integer',
            'user_id'   => 'integer',
            'bundle_id' => 'integer',
        ];
    }

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
        $flights = self::where('enabled', true);
        foreach ($days as $day) {
            $flights = $flights->whereRaw('(days & ?) > 0', [$day]);
        }

        return $flights;
    }

    /**
     * Get the flight ident, e.,g JBU1900/C.nn/L.yy
     */
    public function ident(): Attribute
    {
        return Attribute::make(
            get: function ($_, $attrs): string {
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
            get: function ($_, $attrs): string {
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

    /**
     * Normalize departure airport ICAO at write time: uppercase + trim.
     * Single-row write paths (admin form, FlightImporter, manual save) flow
     * through this mutator; the RouteForge bulk-insert path normalizes
     * explicitly in `RouteForgeService::buildFlightAttrs()` because
     * `Model::insert()` bypasses Eloquent's attribute machinery.
     */
    protected function dptAirportId(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $value === null
                ? null
                : strtoupper(trim($value)),
        );
    }

    /**
     * Normalize arrival airport ICAO at write time: uppercase + trim.
     * Same single-row-vs-bulk caveat as `dptAirportId()`.
     */
    protected function arrAirportId(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $value === null
                ? null
                : strtoupper(trim($value)),
        );
    }

    /**
     * Canonicalize `route_code` to NULL when the input is empty / '0' / 0.
     *
     * Single source of truth for the strict-duplicate namespace: a missing
     * `route_code` is stored as NULL, never as '' or '0'. The `flights._dup_key`
     * generated column (UNIQUE index `flights_dup_key_unique`) depends on this
     * canonicalization so the index key is deterministic across historical and
     * new rows.
     *
     * Applies on `$flight->route_code = X` and during `$flight->fill([...])`.
     * `Model::insert()` (used by the RouteForge bulk-insert path) calls
     * `fill()` first inside `RouteForgeService::buildFlightAttrs()`, so this
     * mutator is in effect for that path as well.
     */
    protected function routeCode(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): ?string => self::canonicalizeRoutePart($value),
        );
    }

    /**
     * Canonicalize `route_leg` to NULL when the input is empty / '0' / 0,
     * otherwise cast to int.
     *
     * Replaces the legacy `'route_leg' => 'integer'` cast, which converted
     * empty strings to `0` and broke the strict-duplicate namespace (two rows
     * with `route_leg = '' and route_leg = 0` would store as the same `0`,
     * blocking distinct "absent" vs "leg-zero" semantics; in practice the
     * codebase has no leg-zero so collapsing both to NULL is correct).
     */
    protected function routeLeg(): Attribute
    {
        return Attribute::make(
            get: static fn (mixed $value): ?int => $value === null ? null : (int) $value,
            set: static function (mixed $value): ?int {
                $canonical = self::canonicalizeRoutePart($value);

                return $canonical === null ? null : (int) $canonical;
            },
        );
    }

    /**
     * Canonicalize `load_factor` — convert blank strings to null
     * so MySQL strict mode doesn't reject them for the DECIMAL column.
     * Also handles float casting now that the `double` cast is removed.
     */
    protected function loadFactor(): Attribute
    {
        return Attribute::make(
            get: static fn (mixed $value): ?float => $value === null ? null : (float) $value,
            set: static fn (mixed $value): ?float => blank($value) ? null : (float) $value,
        );
    }

    /**
     * Canonicalize `load_factor_variance` — convert blank strings to null
     * so MySQL strict mode doesn't reject them for the DECIMAL column.
     * Also handles float casting now that the `double` cast is removed.
     */
    protected function loadFactorVariance(): Attribute
    {
        return Attribute::make(
            get: static fn (mixed $value): ?float => $value === null ? null : (float) $value,
            set: static fn (mixed $value): ?float => blank($value) ? null : (float) $value,
        );
    }

    /**
     * Collapse null / '' / 0 / '0' to canonical NULL for route-key fields.
     *
     * Used by `routeCode()` and `routeLeg()` mutators above. The strict-equal
     * `in_array(..., strict: true)` matters: it distinguishes `0` (int zero,
     * the legacy "leg zero or empty" sentinel) from `'0'` (string), both of
     * which we collapse, from a real string `'abc'` that we preserve.
     *
     * Backed enums (e.g. `PirepStatus::DIVERTED` set on `route_code` by the
     * diversion handler) are coerced via their backing `->value`. Pure unit
     * enums fall back to their `->name`. This keeps legacy callers working
     * without forcing them to pre-stringify enum values.
     */
    private static function canonicalizeRoutePart(mixed $value): ?string
    {
        if (in_array($value, [null, '', 0, '0'], true)) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            $string = (string) $value->value;
        } elseif ($value instanceof UnitEnum) {
            $string = $value->name;
        } elseif (is_scalar($value) || $value instanceof Stringable) {
            $string = (string) $value;
        } else {
            // Non-stringable object — defer to PHP's cast and let it raise.
            // Callers passing arbitrary objects into route_code/route_leg are
            // out of contract and should fail loudly rather than silently NULL.
            $string = (string) $value;
        }

        // Re-check after coercion: an enum whose value is '' or '0' (unlikely
        // but possible) collapses to NULL same as a literal '' input.
        return ($string === '' || $string === '0') ? null : $string;
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
                    return Days::getDaysMask($value);
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

    /**
     * Resolve the subfleets shown for this flight given a user.
     *
     * Pinned subfleets win. If none are pinned, fall back to:
     *   - airline subfleets when `flights.only_company_aircraft` is on
     *   - all user-allowed subfleets otherwise
     *
     * User access constraints (rank / type rating) are applied throughout.
     * Use this in single-flight controllers; list endpoints use the
     * `withAccessibleSubfleets` scope which skips the fallback.
     */
    public function accessibleSubfleetsFor(User $user, array $with = []): Collection
    {
        $pinned = Subfleet::query()
            ->allowedFor($user)
            ->whereHas('flights', fn ($q) => $q->whereKey($this->id))
            ->with($with)
            ->get();

        if ($pinned->isNotEmpty()) {
            return $pinned;
        }

        $fallback = Subfleet::query()->allowedFor($user)->with($with);
        if (setting('flights.only_company_aircraft', false)) {
            $fallback->where('airline_id', $this->airline_id);
        }

        return $fallback->get();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(FlightBundle::class, 'bundle_id');
    }

    public function owner(): MorphTo
    {
        return $this->morphTo('owner', 'owner_type', 'owner_id');
    }

    /*
     * Query scopes
     */
    /**
     * Backwards-compatible alias for the renamed scope. Equivalent to
     * `Flight::visible()`. Kept indefinitely for module compatibility.
     */
    #[Scope]
    #[Deprecated(message: 'use visible()')]
    protected function active(Builder $query): Builder
    {
        return $query->where('visible', true);
    }

    /**
     * Pilot-facing visibility scope. The `visible` column is cron-managed combined state:
     * computed nightly by `App\Cron\Nightly\SetVisibleFlights` (and via a queued
     * `RecomputeBundleVisibility` job dispatched by `BundleObserver` on bundle
     * save/restore) as `flight.enabled AND bundle.enabled AND
     * in_effective_window`. Admin code SHALL NOT write to `flights.visible` directly;
     * toggle `flights.enabled` instead.
     */
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

    /**
     * Eager-load subfleets, their aircraft, and their fares constrained to the
     * user's access policy. No flight context is passed to the aircraft scope
     * here — airport restriction is a per-flight concern handled by
     * single-flight callers via `Aircraft::allowedFor($user, $flight)`. Rank,
     * type-rating, and bid-block constraints still apply.
     *
     * `fares` is eager-loaded because callers commonly run the result through
     * `FareService::getReconciledFaresForFlight()`, which reads
     * `$subfleet->fares`. Without that load, lazy-loading kicks in and trips
     * `preventLazyLoading()` in non-prod environments.
     */
    #[Scope]
    protected function withAccessibleSubfleets(Builder $query, User $user): Builder
    {
        return $query->with([
            'subfleets' => fn ($sq) => $sq->allowedFor($user)->with([
                'aircraft' => fn ($aq) => $aq->allowedFor($user),
                'aircraft.bid',
                'fares',
            ]),
        ]);
    }
}
