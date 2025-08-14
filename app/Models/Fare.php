<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int                             $id
 * @property string                          $code
 * @property string                          $name
 * @property float|null                      $price
 * @property float|null                      $cost
 * @property int|null                        $capacity
 * @property int|null                        $type
 * @property string|null                     $notes
 * @property bool                            $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Flight> $flights
 * @property-read int|null $flights_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Subfleet> $subfleets
 * @property-read int|null $subfleets_count
 *
 * @method static \Database\Factories\FareFactory                    factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fare newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fare newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fare onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fare query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fare whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fare whereCapacity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fare whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fare whereCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fare whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fare whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fare whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fare whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fare whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fare wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fare whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fare whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fare withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fare withoutTrashed()
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
        'cost',
        'capacity',
        'count',
        'notes',
        'active',
    ];

    public static $rules = [
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
        return $this->belongsToMany(Subfleet::class, 'subfleet_fare')->withPivot('price', 'cost', 'capacity');
    }

    public function flights(): BelongsToMany
    {
        return $this->belongsToMany(Flight::class, 'flight_fare')->withPivot('price', 'cost', 'capacity');
    }

    protected function casts(): array
    {
        return [
            'price'    => 'float',
            'cost'     => 'float',
            'capacity' => 'integer',
            'count'    => 'integer',
            'type'     => 'integer',
            'active'   => 'boolean',
        ];
    }
}
