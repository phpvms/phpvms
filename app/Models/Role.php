<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int id
 * @property string name
 * @property string guard_name
 * @property bool disable_activity_checks
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Role extends SpatieRole
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'id',
        'name',
        'guard_name',
        'disable_activity_checks',
    ];

    /**
     * Validation rules
     *
     * @var array
     */
    public static $rules = [
        'name'       => 'required',
        'guard_name' => 'required',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
