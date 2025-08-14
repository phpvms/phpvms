<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property string     $id
 * @property string     $name
 * @property int        $type
 * @property float|null $lat
 * @property float|null $lon
 * @property float|null $freq
 *
 * @method static \Database\Factories\NavdataFactory                    factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Navdata newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Navdata newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Navdata query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Navdata whereFreq($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Navdata whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Navdata whereLat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Navdata whereLon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Navdata whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Navdata whereType($value)
 *
 * @mixin \Eloquent
 */
class Navdata extends Model
{
    use HasFactory;

    public $table = 'navdata';

    protected $keyType = 'string';

    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'type',
        'lat',
        'lon',
        'freq',
    ];

    /**
     * Make sure the ID is in all caps
     */
    public function id(): Attribute
    {
        return Attribute::make(
            set: fn ($id) => strtoupper($id)
        );
    }

    protected function casts(): array
    {
        return [
            'type' => 'integer',
            'lat'  => 'float',
            'lon'  => 'float',
            'freq' => 'float',
        ];
    }
}
