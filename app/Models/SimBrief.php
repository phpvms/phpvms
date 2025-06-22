<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * @property string      $id                   The Simbrief OFP ID
 * @property int         $user_id              The user that generated this
 * @property string      $flight_id            Optional, if attached to a flight, removed if attached to PIREP
 * @property string      $pirep_id             Optional, if attached to a PIREP, removed if attached to flight
 * @property string      $aircraft_id          The aircraft this is for
 * @property string      $acars_xml
 * @property string      $ofp_xml
 * @property string      $ofp_html
 * @property string      $fare_data            JSON string of the fare data that was generated
 * @property Collection  $images
 * @property Collection  $files
 * @property Flight      $flight
 * @property User        $user
 * @property SimBriefXML $xml
 * @property Aircraft    $aircraft
 * @property string      $acars_flightplan_url
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

    /** @var \App\Models\SimBriefXML Store a cached version of the XML object */
    private \SimpleXMLElement|bool|null $xml_instance = null;

    /**
     * Return a SimpleXML object of the $ofp_xml
     */
    protected function xml(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(get: function (): null|\SimpleXMLElement|bool {
            if (empty($this->attributes['ofp_xml'])) {
                return null;
            }
            if (!$this->xml_instance) {
                $this->xml_instance = simplexml_load_string(
                    (string) $this->attributes['ofp_xml'],
                    SimBriefXML::class
                );
            }

            return $this->xml_instance;
        });
    }

    /**
     * Returns a list of images
     */
    protected function images(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(get: fn () => $this->xml->getImages());
    }

    /**
     * Return all of the flight plans
     */
    protected function files(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(get: fn () => $this->xml->getFlightPlans());
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
