<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int         $id
 * @property string      $icao
 * @property string      $name
 * @property string|null $details
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, SimBriefAirframe> $sbairframes
 * @property-read int|null $sbairframes_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefAircraft newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefAircraft newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefAircraft query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefAircraft whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefAircraft whereDetails($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefAircraft whereIcao($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefAircraft whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefAircraft whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefAircraft whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
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
