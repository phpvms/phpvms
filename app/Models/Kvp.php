<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property string $key
 * @property string $value
 *
 * @method static Builder<static>|Kvp newModelQuery()
 * @method static Builder<static>|Kvp newQuery()
 * @method static Builder<static>|Kvp query()
 * @method static Builder<static>|Kvp whereKey($value)
 * @method static Builder<static>|Kvp whereValue($value)
 *
 * @mixin \Eloquent
 */
#[WithoutIncrementing]
#[WithoutTimestamps]
class Kvp extends Model
{
    public $table = 'kvp';

    protected $keyType = 'string';

    public $fillable = [
        'key',
        'value',
    ];
}
