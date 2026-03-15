<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int         $id
 * @property string      $pirep_id
 * @property int         $user_id
 * @property string      $comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Pirep|null $pirep
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepComment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepComment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepComment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepComment whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepComment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepComment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepComment wherePirepId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepComment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepComment whereUserId($value)
 *
 * @mixin \Eloquent
 */
class PirepComment extends Model
{
    public $table = 'pirep_comments';

    protected $fillable = [
        'pirep_id',
        'user_id',
        'comment',
    ];

    public static array $rules = [
        'comment' => 'required',
    ];

    public function pirep(): BelongsTo
    {
        return $this->belongsTo(Pirep::class, 'pirep_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
