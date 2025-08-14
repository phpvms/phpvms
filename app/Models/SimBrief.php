<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string                          $id
 * @property int                             $user_id
 * @property string|null                     $flight_id
 * @property string|null                     $pirep_id
 * @property int|null                        $aircraft_id
 * @property string                          $acars_xml
 * @property string                          $ofp_xml
 * @property string|null                     $fare_data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Aircraft|null $aircraft
 * @property-read mixed $files
 * @property-read \App\Models\Flight|null $flight
 * @property-read mixed $images
 * @property-read \App\Models\Pirep|null $pirep
 * @property-read \App\Models\User|null $user
 * @property-read mixed $xml
 *
 * @method static \Database\Factories\SimBriefFactory                    factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief whereAcarsXml($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief whereAircraftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief whereFareData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief whereFlightId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief whereOfpXml($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief wherePirepId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBrief whereUserId($value)
 *
 * @mixin \Eloquent
 */
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
        'acars_xml',
        'ofp_xml',
        'fare_data',
        'created_at',
        'updated_at',
    ];

    /** @var SimBriefXML Store a cached version of the XML object */
    private $xml_instance;

    /**
     * Return a SimpleXML object of the $ofp_xml
     */
    protected function xml(): Attribute
    {
        return Attribute::make(get: function () {
            if (empty($this->attributes['ofp_xml'])) {
                return null;
            }
            if (!$this->xml_instance) {
                $this->xml_instance = simplexml_load_string(
                    $this->attributes['ofp_xml'],
                    SimBriefXML::class
                );
            }

            return $this->xml_instance;
        });
    }

    /**
     * Returns a list of images
     */
    protected function images(): Attribute
    {
        return Attribute::make(get: function () {
            return $this->xml->getImages();
        });
    }

    /**
     * Return all of the flight plans
     */
    protected function files(): Attribute
    {
        return Attribute::make(get: function () {
            return $this->xml->getFlightPlans();
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
