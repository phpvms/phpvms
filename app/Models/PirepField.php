<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int         $id
 * @property string      $name
 * @property string|null $slug
 * @property string|null $description
 * @property bool|null   $required
 * @property int|null    $pirep_source
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepField newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepField newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepField query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepField whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepField whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepField whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepField wherePirepSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepField whereRequired($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepField whereSlug($value)
 *
 * @mixin \Eloquent
 */
class PirepField extends Model
{
    use LogsActivity;

    public $table = 'pirep_fields';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'required',
        'pirep_source',
    ];

    public static $rules = [
        'name'        => 'required',
        'description' => 'nullable',
    ];

    /**
     * When setting the name attribute, also set the slug
     */
    public function name(): Attribute
    {
        return Attribute::make(
            set: fn ($name) => [
                'name' => $name,
                'slug' => Str::slug($name),
            ]
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
        ];
    }
}
