<?php

namespace App\Models;

use App\Contracts\Model;
use App\Observers\AirportObserver;
use App\Traits\ExpensableTrait;
use App\Traits\FilesTrait;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Kyslik\ColumnSortable\Sortable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class Airport
 *
 * @property string      $id
 * @property string|null $iata
 * @property string      $icao
 * @property string      $name
 * @property string|null $location
 * @property string|null $region
 * @property string|null $country
 * @property string|null $timezone
 * @property bool        $hub
 * @property string|null $notes
 * @property float|null  $lat
 * @property float|null  $lon
 * @property int|null    $elevation
 * @property float|null  $ground_handling_cost
 * @property float|null  $fuel_100ll_cost
 * @property float|null  $fuel_jeta_cost
 * @property float|null  $fuel_mogas_cost
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Collection<int, Aircraft> $aircraft
 * @property-read int|null $aircraft_count
 * @property-read Collection<int, Flight> $arrivals
 * @property-read int|null $arrivals_count
 * @property-read Collection<int, Flight> $departures
 * @property-read int|null $departures_count
 * @property-read mixed $description
 * @property-read Collection<int, Expense> $expenses
 * @property-read int|null $expenses_count
 * @property-read Collection<int, File> $files
 * @property-read int|null $files_count
 * @property-read mixed $full_name
 * @property-read Collection<int, User> $pilots
 * @property-read int|null $pilots_count
 * @property mixed $tz
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static \Database\Factories\AirportFactory                    factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport sortable($defaultParameters = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport whereElevation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport whereFuel100llCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport whereFuelJetaCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport whereFuelMogasCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport whereGroundHandlingCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport whereHub($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport whereIata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport whereIcao($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport whereLat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport whereLon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport whereRegion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport whereTimezone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Airport withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[ObservedBy(AirportObserver::class)]
class Airport extends Model
{
    use ExpensableTrait;
    use FilesTrait;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;
    use Sortable;

    public $table = 'airports';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'iata',
        'icao',
        'name',
        'location',
        'region',
        'country',
        'lat',
        'lon',
        'elevation',
        'hub',
        'timezone',
        'tz',
        'ground_handling_cost',
        'fuel_100ll_cost',
        'fuel_jeta_cost',
        'fuel_mogas_cost',
        'notes',
    ];

    public $sortable = [
        'id',
        'iata',
        'icao',
        'name',
        'hub',
        'notes',
        'elevation',
        'location',
        'region',
        'country',
    ];

    /**
     * Capitalize the ICAO
     */
    public function icao(): Attribute
    {
        return Attribute::make(
            set: fn ($icao) => [
                'id'   => strtoupper($icao),
                'icao' => strtoupper($icao),
            ]
        );
    }

    /**
     * Capitalize the IATA code
     */
    public function iata(): Attribute
    {
        return Attribute::make(
            set: fn ($iata) => strtoupper($iata)
        );
    }

    /**
     * Return full name like:
     * KJFK - John F Kennedy
     */
    public function fullName(): Attribute
    {
        return Attribute::make(
            get: fn ($_, $attrs) => $this->icao.' - '.$this->name
        );
    }

    /**
     * Return full description like:
     * KJFK/JFK - John F Kennedy (hub)
     */
    public function description(): Attribute
    {
        return Attribute::make(
            get: fn ($_, $attrs) => $attrs['icao']
                .(empty($attrs['iata']) ? '' : '/'.$attrs['iata'])
                .' - '.$attrs['name']
                .($attrs['hub'] ? ' (hub)' : '')
        );
    }

    /**
     * Shortcut for timezone
     */
    public function tz(): Attribute
    {
        return Attribute::make(
            get: fn ($_, $attrs) => $attrs['timezone'],
            set: fn ($value) => [
                'timezone' => $value,
            ]
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Relationships
     */
    public function departures(): HasMany
    {
        return $this->hasMany(Flight::class, 'dpt_airport_id');
    }

    public function arrivals(): HasMany
    {
        return $this->hasMany(Flight::class, 'arr_airport_id');
    }

    public function aircraft(): HasMany
    {
        return $this->hasMany(Aircraft::class, 'airport_id');
    }

    public function pilots(): HasMany
    {
        // Users currently at this airport
        return $this->hasMany(User::class, 'curr_airport_id');
    }

    public function users(): HasMany
    {
        // Users based at this airport
        return $this->hasMany(User::class, 'home_airport_id');
    }

    /**
     * Marker scope. Airport has no `status`/`active` column, so this is
     * equivalent to the SoftDeletes default (excludes trashed).
     *
     * Reserved for cross-model consistency — Phase 4 (User domain) and
     * Phase 6 (Flight domain) will add `active()` scopes to `User`,
     * `Airline`, and other models, at which point reading
     * `Airport::active()` will match the convention naturally.
     */
    #[Scope]
    protected function active(Builder $q): Builder
    {
        return $q->whereNull('deleted_at');
    }

    /**
     * Filter to hub airports only.
     */
    #[Scope]
    protected function byHub(Builder $q): Builder
    {
        return $q->where('hub', true);
    }

    /**
     * Order results by ICAO ascending.
     */
    #[Scope]
    protected function orderByIcao(Builder $q): Builder
    {
        return $q->orderBy('icao', 'asc');
    }

    /**
     * URLs typically use a lowercase ICAO (e.g. /api/airports/kjfk) but
     * the table stores ICAO uppercased. Override the default Eloquent
     * route binding so case-insensitive lookups resolve correctly.
     */
    public function resolveRouteBinding($value, $field = null): ?\Illuminate\Database\Eloquent\Model
    {
        return $this->resolveRouteBindingQuery($this, strtoupper((string) $value), $field)->first();
    }

    protected function casts(): array
    {
        return [
            'lat'                  => 'float',
            'lon'                  => 'float',
            'hub'                  => 'boolean',
            'ground_handling_cost' => 'float',
            'fuel_100ll_cost'      => 'float',
            'fuel_jeta_cost'       => 'float',
            'fuel_mogas_cost'      => 'float',
        ];
    }
}
