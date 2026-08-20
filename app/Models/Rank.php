<?php

namespace App\Models;

use App\Contracts\Model;
use App\Traits\HasAssets;
use Database\Factories\RankFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Kyslik\ColumnSortable\Sortable;
use Override;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int          $id
 * @property string       $name
 * @property string|null  $image_url
 * @property int          $hours
 * @property numeric|null $acars_base_pay_rate
 * @property numeric|null $manual_base_pay_rate
 * @property bool|null    $auto_approve_acars
 * @property bool|null    $auto_approve_manual
 * @property bool|null    $auto_promote
 * @property int|null     $auto_approve_above_score
 * @property int|null     $auto_approve_score
 * @property Carbon|null  $created_at
 * @property Carbon|null  $updated_at
 * @property Carbon|null  $deleted_at
 * @property Pivot        $pivot
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Collection<int, Subfleet> $subfleets
 * @property-read int|null $subfleets_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static RankFactory          factory($count = null, $state = [])
 * @method static Builder<static>|Rank newModelQuery()
 * @method static Builder<static>|Rank newQuery()
 * @method static Builder<static>|Rank onlyTrashed()
 * @method static Builder<static>|Rank query()
 * @method static Builder<static>|Rank sortable($defaultParameters = null)
 * @method static Builder<static>|Rank whereAcarsBasePayRate($value)
 * @method static Builder<static>|Rank whereAutoApproveAboveScore($value)
 * @method static Builder<static>|Rank whereAutoApproveAcars($value)
 * @method static Builder<static>|Rank whereAutoApproveManual($value)
 * @method static Builder<static>|Rank whereAutoApproveScore($value)
 * @method static Builder<static>|Rank whereAutoPromote($value)
 * @method static Builder<static>|Rank whereCreatedAt($value)
 * @method static Builder<static>|Rank whereDeletedAt($value)
 * @method static Builder<static>|Rank whereHours($value)
 * @method static Builder<static>|Rank whereId($value)
 * @method static Builder<static>|Rank whereImageUrl($value)
 * @method static Builder<static>|Rank whereManualBasePayRate($value)
 * @method static Builder<static>|Rank whereName($value)
 * @method static Builder<static>|Rank whereUpdatedAt($value)
 * @method static Builder<static>|Rank withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Rank withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Rank extends Model
{
    use HasAssets;

    /** @use HasFactory<RankFactory> */
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

    /** The rank badge lives in the `rank` slot, keyed on the rank id. */
    public function assetSlot(): string
    {
        return Asset::SLOT_RANK;
    }

    /**
     * The badge's URL: the rank's asset when it has one, falling back to the
     * legacy `image_url` column.
     *
     * The column is only reached on an install whose data migration could not
     * move the value — a file that had already been deleted, or a site-relative
     * path we do not host on the assets disk. Rendering the old value there is
     * better than rendering nothing.
     */
    public function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function ($value): ?string {
                $url = $this->assetUrl();

                if ($url !== null) {
                    return $url;
                }

                if (!filled($value)) {
                    return null;
                }

                if (str_contains($value, 'http')) {
                    return $value;
                }

                return url($value);
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

    #[Override]
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
