<?php

namespace App\Models;

use App\Contracts\Model;

class SimBriefLayout extends Model
{
    public $table = 'simbrief_layouts';

    protected $fillable = [
        'id',
        'name',
        'name_long',
    ];

    protected $casts = [
        'id'        => 'string',
        'name'      => 'string',
        'name_long' => 'string',
    ];

    public static array $rules = [
        'id'        => 'required|string',
        'name'      => 'required|string',
        'name_long' => 'required|string',
    ];
}
