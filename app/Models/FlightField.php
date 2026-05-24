<?php

namespace App\Models;

use App\Contracts\Model;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
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
 * @method static \Database\Factories\FlightFieldFactory                    factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightField newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightField newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightField query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightField whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightField whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightField whereSlug($value)
 *
 * @mixin \Eloquent
 */
#[WithoutTimestamps]
class FlightField extends Model
{
    use HasFactory;
    use HasSlug;

    public $table = 'flight_fields';

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
            set: fn ($name): array => [
                'name' => $name,
                'slug' => Str::slug($name),
            ]
        );
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'required' => 'boolean',
        ];
    }
}
