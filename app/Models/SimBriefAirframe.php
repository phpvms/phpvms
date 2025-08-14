<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                             $id
 * @property string                          $icao
 * @property string                          $name
 * @property string|null                     $airframe_id
 * @property int|null                        $source
 * @property string|null                     $details
 * @property string|null                     $options
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SimBriefAircraft|null $sbaircraft
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefAirframe newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefAirframe newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefAirframe query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefAirframe whereAirframeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefAirframe whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefAirframe whereDetails($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefAirframe whereIcao($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefAirframe whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefAirframe whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefAirframe whereOptions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefAirframe whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefAirframe whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
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

    protected function casts(): array
    {
        return [
            'icao' => 'string',
            'name' => 'string',
        ];
    }
}
