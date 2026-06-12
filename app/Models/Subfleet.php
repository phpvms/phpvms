<?php

namespace App\Models;

use App\Contracts\Model;
use App\Enums\AircraftStatus;
use App\Enums\FlightType;
use App\Enums\FuelType;
use App\Observers\SubfleetObserver;
use App\Support\SubfleetAccessPolicy;
use App\Traits\ExpensableTrait;
use App\Traits\FilesTrait;
use Database\Factories\SubfleetFactory;
use Deprecated;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Kyslik\ColumnSortable\Sortable;
use Override;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int                                                  $id
 * @property int|null                                             $airline_id
 * @property string|null                                          $hub_id
 * @property string                                               $type
 * @property string|null                                          $simbrief_type
 * @property string                                               $name
 * @property float|null                                           $cost_block_hour
 * @property float|null                                           $cost_delay_minute
 * @property FuelType|null                                        $fuel_type
 * @property float|null                                           $ground_handling_multiplier
 * @property float|null                                           $cargo_capacity
 * @property float|null                                           $fuel_capacity
 * @property float|null                                           $gross_weight
 * @property int|null                                             $cruise_speed
 * @property int|null                                             $max_range_nm
 * @property \Illuminate\Support\Collection<int, FlightType>|null $route_types
 * @property Carbon|null                                          $created_at
 * @property Carbon|null                                          $updated_at
 * @property Carbon|null                                          $deleted_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Collection<int, Aircraft> $aircraft
 * @property-read int|null $aircraft_count
 * @property-read Airline|null $airline
 * @property-read Collection<int, Expense> $expenses
 * @property-read int|null $expenses_count
 * @property-read Collection<int, Fare> $fares
 * @property-read int|null $fares_count
 * @property-read Collection<int, File> $files
 * @property-read int|null $files_count
 * @property-read Collection<int, Flight> $flights
 * @property-read int|null $flights_count
 * @property-read Airport|null $home
 * @property-read Airport|null $hub
 * @property-read Collection<int, Rank> $ranks
 * @property-read int|null $ranks_count
 * @property-read Collection<int, Typerating> $typeratings
 * @property-read int|null $typeratings_count
 *
 * @method static SubfleetFactory          factory($count = null, $state = [])
 * @method static Builder<static>|Subfleet newModelQuery()
 * @method static Builder<static>|Subfleet newQuery()
 * @method static Builder<static>|Subfleet onlyTrashed()
 * @method static Builder<static>|Subfleet query()
 * @method static Builder<static>|Subfleet sortable($defaultParameters = null)
 * @method static Builder<static>|Subfleet whereAirlineId($value)
 * @method static Builder<static>|Subfleet whereCargoCapacity($value)
 * @method static Builder<static>|Subfleet whereCostBlockHour($value)
 * @method static Builder<static>|Subfleet whereCostDelayMinute($value)
 * @method static Builder<static>|Subfleet whereCreatedAt($value)
 * @method static Builder<static>|Subfleet whereDeletedAt($value)
 * @method static Builder<static>|Subfleet whereFuelCapacity($value)
 * @method static Builder<static>|Subfleet whereFuelType($value)
 * @method static Builder<static>|Subfleet whereGrossWeight($value)
 * @method static Builder<static>|Subfleet whereGroundHandlingMultiplier($value)
 * @method static Builder<static>|Subfleet whereHubId($value)
 * @method static Builder<static>|Subfleet whereId($value)
 * @method static Builder<static>|Subfleet whereName($value)
 * @method static Builder<static>|Subfleet whereSimbriefType($value)
 * @method static Builder<static>|Subfleet whereType($value)
 * @method static Builder<static>|Subfleet whereUpdatedAt($value)
 * @method static Builder<static>|Subfleet withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Subfleet withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[ObservedBy(SubfleetObserver::class)]
class Subfleet extends Model
{
    use ExpensableTrait;
    use FilesTrait;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;
    use Sortable;

    public $fillable = [
        'airline_id',
        'hub_id',
        'type',
        'simbrief_type',
        'name',
        'fuel_type',
        'cost_block_hour',
        'cost_delay_minute',
        'ground_handling_multiplier',
        'cargo_capacity',
        'fuel_capacity',
        'gross_weight',
        'cruise_speed',
        'max_range_nm',
        'route_types',
    ];

    public $table = 'subfleets';

    public $sortable = [
        'id',
        'airline_id',
        'hub_id',
        'type',
        'name',
    ];

    public function type(): Attribute
    {
        return Attribute::make(
            set: fn ($type): string|array => str_replace([' ', ','], ['-', ''], $type)
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
    public function aircraft(): HasMany
    {
        return $this->hasMany(Aircraft::class, 'subfleet_id')->where(
            'status',
            AircraftStatus::ACTIVE
        );
    }

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class, 'airline_id');
    }

    public function home(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'hub_id');
    }

    #[Deprecated(message: 'use home()')]
    public function hub(): BelongsTo
    {
        return $this->home();
    }

    public function fares(): BelongsToMany
    {
        return $this->belongsToMany(Fare::class, 'subfleet_fare')->withPivot(
            'price',
            'cost',
            'capacity'
        );
    }

    public function flights(): BelongsToMany
    {
        return $this->belongsToMany(Flight::class, 'flight_subfleet');
    }

    public function ranks(): BelongsToMany
    {
        return $this->belongsToMany(Rank::class, 'subfleet_rank')
            ->withPivot('acars_pay', 'manual_pay');
    }

    public function typeratings(): BelongsToMany
    {
        return $this->belongsToMany(
            Typerating::class,
            'typerating_subfleet',
            'subfleet_id',
            'typerating_id'
        );
    }

    /**
     * Restrict to subfleets the given user is allowed to operate, based on
     * rank and type-rating settings. See SubfleetAccessPolicy.
     */
    #[Scope]
    protected function allowedFor(Builder $query, User $user): Builder
    {
        return new SubfleetAccessPolicy($user)->applyToSubfleets($query);
    }

    /**
     * The attributes that should be cast to native types.
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'airline_id'                 => 'integer',
            'turn_time'                  => 'integer',
            'cost_block_hour'            => 'float',
            'cost_delay_minute'          => 'float',
            'fuel_type'                  => FuelType::class,
            'ground_handling_multiplier' => 'float',
            'cargo_capacity'             => 'float',
            'fuel_capacity'              => 'float',
            'gross_weight'               => 'float',
            'cruise_speed'               => 'integer',
            'max_range_nm'               => 'integer',
            'route_types'                => AsEnumCollection::of(FlightType::class),
        ];
    }
}
