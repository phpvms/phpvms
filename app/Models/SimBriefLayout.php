<?php

namespace App\Models;

use App\Contracts\Model;

/**
 * @property string                          $id
 * @property string                          $name
 * @property string                          $name_long
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefLayout newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefLayout newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefLayout query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefLayout whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefLayout whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefLayout whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefLayout whereNameLong($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimBriefLayout whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class SimBriefLayout extends Model
{
    public $table = 'simbrief_layouts';

    protected $fillable = [
        'id',
        'name',
        'name_long',
    ];

    public static array $rules = [
        'id'        => 'required|string',
        'name'      => 'required|string',
        'name_long' => 'required|string',
    ];

    protected function casts(): array
    {
        return [
            'id'        => 'string',
            'name'      => 'string',
            'name_long' => 'string',
        ];
    }
}
