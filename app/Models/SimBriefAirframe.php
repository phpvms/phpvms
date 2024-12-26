<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimBriefAirframe extends Model
{
    public $table = 'simbrief_airframes';

    protected $fillable = [
        'id',
        'icao',
        'name',
        'airframe_id',
        'source',
        'details',
        'options',
    ];

    protected $casts = [
        'icao' => 'string',
        'name' => 'string',
    ];

    public static array $rules = [
        'icao'        => 'required|string',
        'name'        => 'required|string',
        'airframe_id' => 'nullable',
        'source'      => 'nullable',
        'details'     => 'nullable',
        'options'     => 'nullable',
    ];

    // Relationships
    public function sbaircraft(): BelongsTo
    {
        return $this->belongsTo(SimBriefAircraft::class, 'icao', 'icao');
    }
}
