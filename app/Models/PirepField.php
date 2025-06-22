<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string name
 * @property string slug
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
            set: fn ($name): array => [
                'name' => $name,
                'slug' => \Illuminate\Support\Str::slug($name),
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
