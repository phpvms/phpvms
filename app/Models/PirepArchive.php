<?php

namespace App\Models;

use App\Contracts\Model;
use Database\Factories\PirepArchiveFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * A self-contained snapshot of a filed PIREP's flight, aircraft, and trimmed
 * SimBrief plan, written at file time so detail views survive the source
 * rows being deleted or changed. See `PirepArchiveService` for the blob shape.
 *
 * @property string      $pirep_id
 * @property string|null $flight_id
 * @property Carbon|null $scheduled_arrival_at
 * @property array|null  $flight
 * @property array|null  $aircraft
 * @property array|null  $simbrief
 * @property array|null  $navlog
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Pirep|null $pirep
 *
 * @method static PirepArchiveFactory          factory($count = null, $state = [])
 * @method static Builder<static>|PirepArchive newModelQuery()
 * @method static Builder<static>|PirepArchive newQuery()
 * @method static Builder<static>|PirepArchive query()
 */
#[WithoutIncrementing]
class PirepArchive extends Model
{
    use HasFactory;

    public $table = 'pirep_archive';

    protected $primaryKey = 'pirep_id';

    protected $keyType = 'string';

    public $fillable = [
        'pirep_id',
        'flight_id',
        'scheduled_arrival_at',
        'flight',
        'aircraft',
        'simbrief',
        'navlog',
        'fines',
    ];

    #[Override]
    protected function casts(): array
    {
        return [
            'scheduled_arrival_at' => 'datetime',
            'flight'               => 'array',
            'aircraft'             => 'array',
            'simbrief'             => 'array',
            'navlog'               => 'array',
            'fines'                => 'array',
        ];
    }

    public function pirep(): BelongsTo
    {
        return $this->belongsTo(Pirep::class, 'pirep_id');
    }
}
