<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Model;
use App\Enums\NavaidType;
use Database\Factories\NavdataFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Override;

/**
 * @property string     $id
 * @property string     $name
 * @property NavaidType $type
 * @property float|null $lat
 * @property float|null $lon
 * @property float|null $freq
 *
 * @method static NavdataFactory          factory($count = null, $state = [])
 * @method static Builder<static>|Navdata newModelQuery()
 * @method static Builder<static>|Navdata newQuery()
 * @method static Builder<static>|Navdata query()
 * @method static Builder<static>|Navdata whereFreq($value)
 * @method static Builder<static>|Navdata whereId($value)
 * @method static Builder<static>|Navdata whereLat($value)
 * @method static Builder<static>|Navdata whereLon($value)
 * @method static Builder<static>|Navdata whereName($value)
 * @method static Builder<static>|Navdata whereType($value)
 *
 * @mixin \Eloquent
 */
#[WithoutIncrementing]
#[WithoutTimestamps]
class Navdata extends Model
{
    use HasFactory;

    public $table = 'navdata';

    protected $keyType = 'string';

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
            set: fn ($id) => strtoupper((string) $id)
        );
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'type' => NavaidType::class,
            'lat'  => 'float',
            'lon'  => 'float',
            'freq' => 'float',
        ];
    }
}
