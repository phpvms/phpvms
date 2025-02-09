<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int            user_id
 * @property User           user
 * @property string         provider
 * @property string         token
 * @property string         refresh_token
 * @property \Carbon\Carbon expires_at
 */
class UserOAuthToken extends Model
{
    public $table = 'user_oauth_tokens';

    protected $fillable = [
        'user_id',
        'provider',
        'token',
        'refresh_token',
        'expires_at',
    ];

    protected $casts = [
        'user_id'           => 'integer',
        'provider'          => 'string',
        'token'             => 'string',
        'refresh_token'     => 'string',
        'expires_at'        => 'datetime',
    ];

    public static $rules = [
        'user_id'           => 'required|integer',
        'provider'          => 'required|string',
        'token'             => 'required|string',
        'refresh_token'     => 'required|string',
        'expires_at'        => 'nullable|datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
