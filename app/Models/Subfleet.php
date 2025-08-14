<?php

namespace App\Models;

use App\Contracts\Model;
use App\Models\Enums\AircraftStatus;
use App\Models\Traits\ExpensableTrait;
use App\Models\Traits\FilesTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kyslik\ColumnSortable\Sortable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int                             $id
 * @property int|null                        $airline_id
 * @property string|null                     $hub_id
 * @property string                          $type
 * @property string|null                     $simbrief_type
 * @property string                          $name
 * @property float|null                      $cost_block_hour
 * @property float|null                      $cost_delay_minute
 * @property int|null                        $fuel_type
 * @property float|null                      $ground_handling_multiplier
 * @property float|null                      $cargo_capacity
 * @property float|null                      $fuel_capacity
 * @property float|null                      $gross_weight
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Aircraft> $aircraft
 * @property-read int|null $aircraft_count
 * @property-read \App\Models\Airline|null $airline
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Expense> $expenses
 * @property-read int|null $expenses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fare> $fares
 * @property-read int|null $fares_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\File> $files
 * @property-read int|null $files_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Flight> $flights
 * @property-read int|null $flights_count
 * @property-read \App\Models\Airport|null $home
 * @property-read \App\Models\Airport|null $hub
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Rank> $ranks
 * @property-read int|null $ranks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Typerating> $typeratings
 * @property-read int|null $typeratings_count
 *
 * @method static \Database\Factories\SubfleetFactory                    factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet sortable($defaultParameters = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet whereAirlineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet whereCargoCapacity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet whereCostBlockHour($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet whereCostDelayMinute($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet whereFuelCapacity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet whereFuelType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet whereGrossWeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet whereGroundHandlingMultiplier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet whereHubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet whereSimbriefType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subfleet withoutTrashed()
 *
 * @mixin \Eloquent
 */
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
    ];

    public $table = 'subfleets';

    public $casts = [
        'airline_id'                 => 'integer',
        'turn_time'                  => 'integer',
        'cost_block_hour'            => 'float',
        'cost_delay_minute'          => 'float',
        'fuel_type'                  => 'integer',
        'ground_handling_multiplier' => 'float',
        'cargo_capacity'             => 'float',
        'fuel_capacity'              => 'float',
        'gross_weight'               => 'float',
    ];

    public static $rules = [
        'type'                       => 'required',
        'name'                       => 'required',
        'hub_id'                     => 'nullable',
        'ground_handling_multiplier' => 'nullable|numeric',
    ];

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
            set: fn ($type) => str_replace([' ', ','], ['-', ''], $type)
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

    public function home(): HasOne
    {
        return $this->hasOne(Airport::class, 'id', 'hub_id');
    }

    /**
     * @deprecated use home()
     */
    public function hub(): HasOne
    {
        return $this->hasOne(Airport::class, 'id', 'hub_id');
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
}
