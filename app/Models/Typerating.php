<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Kyslik\ColumnSortable\Sortable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Typerating extends Model
{
    use Sortable;
    use LogsActivity;

    public $table = 'typeratings';

    protected $fillable = [
        'name',
        'type',
        'description',
        'image_url',
        'active',
    ];

    // Validation
    public static $rules = [
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
