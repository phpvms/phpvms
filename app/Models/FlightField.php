<?php

namespace App\Models;

use App\Contracts\Model;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

/**
 * Class FlightField
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $slug
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightField newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightField newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightField query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightField whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightField whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightField whereSlug($value)
 * @method static \Database\Factories\FlightFieldFactory                    factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class FlightField extends Model
{
    use HasFactory;
    use HasSlug;

    public $table = 'flight_fields';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'required',
    ];

    public static array $rules = [
        'name' => 'required',
    ];

    /**
     * When setting the name attribute, also set the slug
     */
    public function name(): Attribute
    {
        return Attribute::make(
            set: fn ($name) => [
                'name' => $name,
                'slug' => Str::slug($name),
            ]
        );
    }

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
        ];
    }
}
