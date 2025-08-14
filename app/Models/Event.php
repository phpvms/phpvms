<?php

namespace App\Models;

use App\Contracts\Model;

/**
 * @property int                             $id
 * @property int                             $type
 * @property string                          $name
 * @property string|null                     $description
 * @property \Illuminate\Support\Carbon      $start_date
 * @property \Illuminate\Support\Carbon      $end_date
 * @property int|null                        $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Event extends Model
{
    public $table = 'events';

    protected $fillable = [
        'type',
        'name',
        'description',
        'start_date',
        'end_date',
        'active',
    ];

    // Validation
    public static $rules = [
        'type'        => 'required|numeric',
        'name'        => 'required|max:250',
        'description' => 'nullable',
        'start_date'  => 'required',
        'end_date'    => 'required',
        'active'      => 'nullable',
    ];

    // Attributes may be defined later if necessary

    // Relationships may be defined later if necessary
    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date'   => 'datetime',
        ];
    }
}
