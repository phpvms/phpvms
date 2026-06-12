<?php

namespace App\Models;

use App\Contracts\Model;
use App\Observers\SimBriefObserver;
use App\Support\Dto\SimBriefOfp\SimBriefOfp;
use Database\Factories\SimBriefFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Override;

/**
 * @property string      $id
 * @property int         $user_id
 * @property string|null $flight_id
 * @property string|null $pirep_id
 * @property int|null    $aircraft_id
 * @property string|null $fare_data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $ofp_json_path
 * @property-read Aircraft|null $aircraft
 * @property-read Collection $files
 * @property-read Flight|null $flight
 * @property-read Collection $images
 * @property-read SimBriefOfp|null $ofp
 * @property-read Pirep|null $pirep
 * @property-read User|null $user
 *
 * @method static SimBriefFactory          factory($count = null, $state = [])
 * @method static Builder<static>|SimBrief newModelQuery()
 * @method static Builder<static>|SimBrief newQuery()
 * @method static Builder<static>|SimBrief query()
 * @method static Builder<static>|SimBrief whereAircraftId($value)
 * @method static Builder<static>|SimBrief whereCreatedAt($value)
 * @method static Builder<static>|SimBrief whereFareData($value)
 * @method static Builder<static>|SimBrief whereFlightId($value)
 * @method static Builder<static>|SimBrief whereId($value)
 * @method static Builder<static>|SimBrief whereOfpJsonPath($value)
 * @method static Builder<static>|SimBrief wherePirepId($value)
 * @method static Builder<static>|SimBrief whereUpdatedAt($value)
 * @method static Builder<static>|SimBrief whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[ObservedBy(SimBriefObserver::class)]
#[WithoutIncrementing]
class SimBrief extends Model
{
    use HasFactory;

    public $table = 'simbrief';

    protected $fillable = [
        'id',
        'user_id',
        'flight_id',
        'aircraft_id',
        'pirep_id',
        'ofp_json_path',
        'fare_data',
        'created_at',
        'updated_at',
    ];

    protected function ofp(): Attribute
    {
        return Attribute::make(get: function (): ?SimBriefOfp {
            if (empty($this->attributes['ofp_json_path'])) {
                return null;
            }

            $ofp = Storage::json($this->attributes['ofp_json_path']);

            return SimBriefOfp::from($ofp);
        });
    }

    /**
     * Returns a list of images
     */
    protected function images(): Attribute
    {
        return Attribute::make(get: function (): Collection {
            $images = collect();
            $base_url = $this->ofp->images->directory;
            foreach ($this->ofp->images->map as $image) {
                $images->push([
                    'name' => $image->name,
                    'url'  => $base_url.$image->link,
                ]);
            }

            return $images;
        });
    }

    /**
     * Return all of the flight plans
     */
    protected function files(): Attribute
    {
        return Attribute::make(get: function (): Collection {
            $flightplans = collect();
            $base_url = $this->ofp->fms_downloads->directory;

            foreach ($this->ofp->fms_downloads->files as $file) {
                $flightplans->push([
                    'name' => $file->name,
                    'url'  => $base_url.$file->link,
                ]);
            }

            return $flightplans;
        });
    }

    /*
     * Relationships
     */
    public function aircraft(): BelongsTo
    {
        return $this->belongsTo(Aircraft::class, 'aircraft_id');
    }

    public function flight(): BelongsTo
    {
        return $this->belongsTo(Flight::class, 'flight_id');
    }

    public function pirep(): BelongsTo
    {
        return $this->belongsTo(Pirep::class, 'pirep_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
        ];
    }
}
