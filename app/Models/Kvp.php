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

    // The kvp table is keyed by `key` (there is no `id` column). Without this,
    // Eloquent's update path targets a nonexistent `id` (`where "id" is null`),
    // so updateOrCreate() fatals with 42703 whenever a key already exists.
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $fillable = [
        'key',
        'value',
    ];
}
