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
 * @property int                             $id
 * @property string                          $name
 * @property string|null                     $image_url
 * @property int                             $hours
 * @property string|null                     $acars_base_pay_rate
 * @property string|null                     $manual_base_pay_rate
 * @property bool|null                       $auto_approve_acars
 * @property bool|null                       $auto_approve_manual
 * @property bool|null                       $auto_promote
 * @property int|null                        $auto_approve_above_score
 * @property int|null                        $auto_approve_score
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Subfleet> $subfleets
 * @property-read int|null $subfleets_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 *
 * @method static \Database\Factories\RankFactory                    factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rank newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rank newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rank onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rank query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rank sortable($defaultParameters = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rank whereAcarsBasePayRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rank whereAutoApproveAboveScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rank whereAutoApproveAcars($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rank whereAutoApproveManual($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rank whereAutoApproveScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rank whereAutoPromote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rank whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rank whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rank whereHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rank whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rank whereImageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rank whereManualBasePayRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rank whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rank whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rank withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rank withoutTrashed()
 *
 * @mixin \Eloquent
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

    public static array $rules = [
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
     * Return image_url always as full uri
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

    protected function casts(): array
    {
        return [
            'hours'               => 'integer',
            'auto_approve_acars'  => 'bool',
            'auto_approve_manual' => 'bool',
            'auto_promote'        => 'bool',
        ];
    }
}
