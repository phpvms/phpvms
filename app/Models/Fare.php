<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string  name
 * @property float   cost
 * @property float   price
 * @property int     code
 * @property int     capacity
 * @property int     count Only when merged with pivot
 * @property int     type
 * @property string  notes
 * @property bool    active
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
