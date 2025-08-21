<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string                          $id
 * @property int                             $offset
 * @property int                             $order
 * @property string                          $key
 * @property string                          $name
 * @property string                          $value
 * @property string|null                     $default
 * @property string|null                     $group
 * @property string|null                     $type
 * @property string|null                     $options
 * @property string|null                     $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereOffset($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereOptions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereValue($value)
 *
 * @mixin \Eloquent
 */
class Setting extends Model
{
    use LogsActivity;

    public $table = 'settings';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'key',
        'value',
        'group',
        'type',
        'options',
        'description',
    ];

    public static array $rules = [
        'name'  => 'required',
        'key'   => 'required',
        'group' => 'required',
    ];

    public static function formatKey($key): string
    {
        return str_replace('.', '_', strtolower($key));
    }

    /**
     * Force formatting the key
     */
    public function id(): Attribute
    {
        return Attribute::make(
            get: fn ($id, $attrs) => self::formatKey(strtolower($id))
        );
    }

    /**
     * Set the key to lowercase
     */
    public function key(): Attribute
    {
        return Attribute::make(
            set: fn ($key) => strtolower($key)
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
