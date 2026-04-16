<?php

namespace App\Models;

use App\Contracts\Model;
use App\Models\Observers\SimBriefObserver;
use App\Support\Dto\SimBriefOfp\SimBriefOfp;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * @property string      $id                   The Simbrief OFP ID
 * @property int         $user_id              The user that generated this
 * @property string      $flight_id            Optional, if attached to a flight, removed if attached to PIREP
 * @property string      $pirep_id             Optional, if attached to a PIREP, removed if attached to flight
 * @property string      $aircraft_id          The aircraft this is for
 * @property string      $ofp_json_path
 * @property string      $fare_data            JSON string of the fare data that was generated
 * @property Collection  $images
 * @property Collection  $files
 * @property Flight      $flight
 * @property User        $user
 * @property Aircraft    $aircraft
 * @property string      $acars_flightplan_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ?SimBriefOfp $ofp
 * @property-read Pirep|null $pirep
 *
 * @method static \Database\Factories\SimBriefFactory                    factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief whereAircraftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief whereFareData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief whereFlightId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief whereOfpJsonPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief wherePirepId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief whereUserId($value)
 */
#[ObservedBy(SimBriefObserver::class)]
class SimBrief extends Model
{
    use HasFactory;

    public $table = 'simbrief';

    public $incrementing = false;

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
        return Attribute::make(get: function () {
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
        return Attribute::make(get: function () {
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
        return Attribute::make(get: function () {
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

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
        ];
    }
}
