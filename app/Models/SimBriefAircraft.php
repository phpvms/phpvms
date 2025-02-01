<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SimBriefAircraft extends Model
{
    public $table = 'simbrief_aircraft';

    protected $fillable = [
        'id',
        'icao',
        'name',
        'details',
    ];

    public static array $rules = [
        'icao'    => 'required|string',
        'name'    => 'required|string',
        'details' => 'nullable',
    ];

    // Relationships
    public function sbairframes(): HasMany
    {
        return $this->hasMany(SimBriefAirframe::class, 'icao', 'icao');
    }
    protected function casts(): array
    {
        return [
            'icao' => 'string',
            'name' => 'string',
        ];
    }
}
