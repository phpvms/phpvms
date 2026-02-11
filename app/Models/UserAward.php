<?php

namespace App\Models;

use App\Contracts\Model;
use App\Events\AwardAwarded;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Kyslik\ColumnSortable\Sortable;

/**
 * @property int                             $id
 * @property int                             $user_id
 * @property int                             $award_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Award|null $award
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \App\Models\User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAward newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAward newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAward query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAward sortable($defaultParameters = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAward whereAwardId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAward whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAward whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAward whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAward whereUserId($value)
 *
 * @mixin \Eloquent
 */
class UserAward extends Model
{
    use Notifiable;
    use Sortable;

    public $table = 'user_awards';

    protected $fillable = [
        'user_id',
        'award_id',
    ];

    protected $dispatchesEvents = [
        'created' => AwardAwarded::class,
    ];

    public $sortable = [
        'award_id',
        'user_id',
        'created_at',
    ];

    /**
     * Relationships
     */
    public function award(): BelongsTo
    {
        return $this->belongsTo(Award::class, 'award_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
