<?php

namespace App\Models;

use App\Contracts\Model;
use App\Enums\FareType;
use Database\Factories\FareFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Override;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int           $id
 * @property string        $code
 * @property string        $name
 * @property float|null    $price
 * @property float|null    $base_price
 * @property float|null    $per_nm
 * @property float|null    $multiplier
 * @property float|null    $cost
 * @property int|null      $capacity
 * @property FareType|null $type
 * @property string|null   $notes
 * @property bool          $active
 * @property Carbon|null   $created_at
 * @property Carbon|null   $updated_at
 * @property Carbon|null   $deleted_at
 * @property Pivot         $pivot
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Collection<int, Flight> $flights
 * @property-read int|null $flights_count
 * @property-read Collection<int, Subfleet> $subfleets
 * @property-read int|null $subfleets_count
 *
 * @method static FareFactory          factory($count = null, $state = [])
 * @method static Builder<static>|Fare newModelQuery()
 * @method static Builder<static>|Fare newQuery()
 * @method static Builder<static>|Fare onlyTrashed()
 * @method static Builder<static>|Fare query()
 * @method static Builder<static>|Fare whereActive($value)
 * @method static Builder<static>|Fare whereCapacity($value)
 * @method static Builder<static>|Fare whereCode($value)
 * @method static Builder<static>|Fare whereCost($value)
 * @method static Builder<static>|Fare whereCreatedAt($value)
 * @method static Builder<static>|Fare whereDeletedAt($value)
 * @method static Builder<static>|Fare whereId($value)
 * @method static Builder<static>|Fare whereName($value)
 * @method static Builder<static>|Fare whereNotes($value)
 * @method static Builder<static>|Fare wherePrice($value)
 * @method static Builder<static>|Fare whereType($value)
 * @method static Builder<static>|Fare whereUpdatedAt($value)
 * @method static Builder<static>|Fare withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Fare withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Fare extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public $table = 'fares';

    protected $fillable = [
        'id',
        'code',
        'name',
        'type',
        'price',
        'base_price',
        'per_nm',
        'multiplier',
        'cost',
        'capacity',
        'notes',
        'active',
    ];

    public static array $rules = [
        'code' => 'required',
        'name' => 'required',
        'type' => 'required',
    ];

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
    public function subfleets(): BelongsToMany
    {
        return $this->belongsToMany(Subfleet::class, 'subfleet_fare')->withPivot('price', 'cost', 'capacity', 'base_price', 'per_nm', 'multiplier');
    }

    public function flights(): BelongsToMany
    {
        return $this->belongsToMany(Flight::class, 'flight_fare')->withPivot('price', 'cost', 'capacity');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'price'      => 'float',
            'base_price' => 'float',
            'per_nm'     => 'float',
            'multiplier' => 'float',
            'cost'       => 'float',
            'capacity'   => 'integer',
            'type'       => FareType::class,
            'active'     => 'boolean',
        ];
    }
}
