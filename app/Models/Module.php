<?php

namespace App\Models;

use App\Contracts\Model;
use Carbon\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string name
 * @property bool   enabled
 * @property Carbon created_at
 * @property Carbon updated_at
 */
class Module extends Model
{
    use LogsActivity;

    public $table = 'modules';

    public $fillable = [
        'name',
        'enabled',
        'created_at',
        'updated_at',
    ];

    public static $rules = [
        'name' => 'required',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logExcept(['created_at', 'updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
