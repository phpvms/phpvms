<?php

namespace App\Models;

use App\Contracts\Model;

/**
 * @property string $key
 * @property string $value
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kvp newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kvp newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kvp query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kvp whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kvp whereValue($value)
 *
 * @mixin \Eloquent
 */
class Kvp extends Model
{
    public $table = 'kvp';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    public $fillable = [
        'key',
        'value',
    ];
}
