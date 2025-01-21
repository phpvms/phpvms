<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kyslik\ColumnSortable\Sortable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string name
 * @property int    hours
 * @property float  manual_base_pay_rate
 * @property float  acars_base_pay_rate
 * @property bool   auto_promote
 * @property bool   auto_approve_acars
 * @property bool   auto_approve_manual
 */
class Rank extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;
    use Sortable;

    public $table = 'ranks';

    protected $fillable = [
        'name',
        'hours',
        'image_url',
        'acars_base_pay_rate',
        'manual_base_pay_rate',
        'auto_approve_acars',
        'auto_approve_manual',
        'auto_promote',
    ];

    protected $casts = [
        'hours'               => 'integer',
        'auto_approve_acars'  => 'bool',
        'auto_approve_manual' => 'bool',
        'auto_promote'        => 'bool',
    ];

    public static $rules = [
        'name'                 => 'required',
        'hours'                => 'required|integer',
        'acars_base_pay_rate'  => 'nullable|numeric',
        'manual_base_pay_rate' => 'nullable|numeric',
    ];

    public $sortable = [
        'id',
        'name',
        'hours',
        'acars_base_pay_rate',
        'manual_base_pay_rate',
    ];

    /**
     * Return image_url always as full uri.
     */
    public function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (!filled($value)) {
                    return null;
                }

                if (str_contains($value, 'http')) {
                    return $value;
                }

                return public_url($value);
            },
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /*
     * Relationships
     */
    public function subfleets(): BelongsToMany
    {
        return $this->belongsToMany(Subfleet::class, 'subfleet_rank')->withPivot('acars_pay', 'manual_pay');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'rank_id');
    }
}
