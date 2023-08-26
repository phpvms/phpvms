<?php

namespace App\Models;

use App\Contracts\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $pirep_id
 * @property string $comment
 * @property int    $user_id
 * @property Pirep  $pirep
 * @property User   $user
 * @property Carbon $created_at
 */
class PirepComment extends Model
{
    public $table = 'pirep_comments';

    protected $fillable = [
        'pirep_id',
        'user_id',
        'comment',
    ];

    public static $rules = [
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
