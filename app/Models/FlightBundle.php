<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Model;
use App\Observers\BundleObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int         $id
 * @property string      $name
 * @property string|null $description
 * @property bool        $enabled
 * @property bool        $visible
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property bool        $is_default
 * @property int|null    $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Flight> $flights
 * @property-read User|null $creator
 * @property int|null $enabled_flights_count
 * @property int|null $disabled_flights_count
 */
#[ObservedBy(BundleObserver::class)]
class FlightBundle extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public $table = 'flight_bundles';

    protected $fillable = [
        'name',
        'description',
        'enabled',
        'visible',
        'start_date',
        'end_date',
        'is_default',
        'created_by',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function flights(): HasMany
    {
        return $this->hasMany(Flight::class, 'bundle_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('visible', true);
    }

    public function hasDates(): bool
    {
        return filled($this->start_date) || filled($this->end_date);
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'enabled'    => 'boolean',
            'visible'    => 'boolean',
            'start_date' => 'datetime',
            'end_date'   => 'datetime',
            'is_default' => 'boolean',
        ];
    }
}
