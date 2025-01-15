<?php

namespace App\Models;

use App\Contracts\Model;

/**
 * @property string key
 * @property string value
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
