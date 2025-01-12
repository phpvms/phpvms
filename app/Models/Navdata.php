<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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

    protected $casts = [
        'type' => 'integer',
        'lat'  => 'float',
        'lon'  => 'float',
        'freq' => 'float',
    ];

    /**
     * Make sure the ID is in all caps.
     */
    public function id(): Attribute
    {
        return Attribute::make(
            set: fn ($id) => strtoupper($id)
        );
    }
}
