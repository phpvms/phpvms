<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property int                             $id
 * @property string                          $name
 * @property string                          $guard_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int                             $disable_activity_checks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 *
 * @method static \Database\Factories\RoleFactory                    factory($count = null, $state = [])
 * @method static Builder<static>|Role                               newModelQuery()
 * @method static Builder<static>|Role                               newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role permission($permissions, $without = false)
 * @method static Builder<static>|Role                               query()
 * @method static Builder<static>|Role                               whereCreatedAt($value)
 * @method static Builder<static>|Role                               whereDisableActivityChecks($value)
 * @method static Builder<static>|Role                               whereGuardName($value)
 * @method static Builder<static>|Role                               whereId($value)
 * @method static Builder<static>|Role                               whereName($value)
 * @method static Builder<static>|Role                               whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role withoutPermission($permissions)
 *
 * @mixin \Eloquent
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
     */
    public static array $rules = [
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
