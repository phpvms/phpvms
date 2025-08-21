<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Kyslik\ColumnSortable\Sortable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int                             $id
 * @property string                          $name
 * @property string                          $type
 * @property string|null                     $description
 * @property string|null                     $image_url
 * @property int                             $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Subfleet> $subfleets
 * @property-read int|null $subfleets_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Typerating newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Typerating newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Typerating query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Typerating sortable($defaultParameters = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Typerating whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Typerating whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Typerating whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Typerating whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Typerating whereImageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Typerating whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Typerating whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Typerating whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Typerating extends Model
{
    use LogsActivity;
    use Sortable;

    public $table = 'typeratings';

    protected $fillable = [
        'name',
        'type',
        'description',
        'image_url',
        'active',
    ];

    // Validation
    public static array $rules = [
        'name'        => 'required',
        'type'        => 'required',
        'description' => 'nullable',
        'image_url'   => 'nullable',
    ];

    public $sortable = [
        'id',
        'name',
        'type',
        'description',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Relationships
    public function subfleets(): BelongsToMany
    {
        return $this->belongsToMany(Subfleet::class, 'typerating_subfleet', 'typerating_id', 'subfleet_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'typerating_user', 'typerating_id', 'user_id');
    }
}
