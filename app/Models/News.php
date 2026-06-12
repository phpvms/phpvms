<?php

namespace App\Models;

use App\Contracts\Model;
use Database\Factories\NewsFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Kyslik\ColumnSortable\Sortable;

/**
 * @property int         $id
 * @property int         $user_id
 * @property string      $subject
 * @property string      $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read User|null $user
 *
 * @method static NewsFactory          factory($count = null, $state = [])
 * @method static Builder<static>|News newModelQuery()
 * @method static Builder<static>|News newQuery()
 * @method static Builder<static>|News query()
 * @method static Builder<static>|News sortable($defaultParameters = null)
 * @method static Builder<static>|News whereBody($value)
 * @method static Builder<static>|News whereCreatedAt($value)
 * @method static Builder<static>|News whereId($value)
 * @method static Builder<static>|News whereSubject($value)
 * @method static Builder<static>|News whereUpdatedAt($value)
 * @method static Builder<static>|News whereUserId($value)
 *
 * @mixin \Eloquent
 */
class News extends Model
{
    use HasFactory;
    use Notifiable;
    use Sortable;

    public $table = 'news';

    protected $fillable = [
        'user_id',
        'subject',
        'body',
    ];

    public $sortable = [
        'id',
        'subject',
        'user_id',
        'created_at',
    ];

    /**
     * Relationships
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
