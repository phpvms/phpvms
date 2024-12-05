<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcarsSource extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['slug', 'name'];

    public function acars() 
    {
        return $this->hasMany(Acars::class);
    }
}
