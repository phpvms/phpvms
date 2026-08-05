<?php

namespace App\Models;

use App\Contracts\Model;
use App\Traits\HasNanoIds;
use Database\Factories\PirepEventFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property string      $id
 * @property string      $pirep_id
 * @property string|null $acars_id
 * @property string|null $client_event_id
 * @property string|null $type
 * @property string      $category
 * @property string|null $phase
 * @property float|null  $lat
 * @property float|null  $lon
 * @property float|null  $altitude_msl
 * @property string|null $log
 * @property array|null  $details
 * @property string|null $sim_time
 * @property Carbon|null $created_at
 * @property-read Pirep       $pirep
 * @property-read Acars|null  $acars
 *
 * @method static PirepEventFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
#[WithoutIncrementing]
class PirepEvent extends Model
{
    use HasFactory;
    use HasNanoIds;

    public $table = 'pirep_events';

    /** No `updated_at` column; rows are immutable after creation. */
    public const UPDATED_AT = null;

    public $fillable = [
        'id',
        'pirep_id',
        'acars_id',
        'client_event_id',
        'type',
        'category',
        'phase',
        'lat',
        'lon',
        'altitude_msl',
        'log',
        'details',
        'sim_time',
        'created_at',
    ];

    #[Override]
    protected function casts(): array
    {
        return [
            'details'      => 'array',
            'lat'          => 'float',
            'lon'          => 'float',
            'altitude_msl' => 'float',
            'created_at'   => 'datetime',
        ];
    }

    /**
     * Relationships
     */
    public function pirep(): BelongsTo
    {
        return $this->belongsTo(Pirep::class, 'pirep_id');
    }

    public function acars(): BelongsTo
    {
        return $this->belongsTo(Acars::class, 'acars_id');
    }
}
