<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                             $id
 * @property int                             $user_id
 * @property string                          $provider
 * @property string                          $token
 * @property string                          $refresh_token
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $is_expired
 * @property-read \App\Models\User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserOAuthToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserOAuthToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserOAuthToken query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserOAuthToken whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserOAuthToken whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserOAuthToken whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserOAuthToken whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserOAuthToken whereRefreshToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserOAuthToken whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserOAuthToken whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserOAuthToken whereUserId($value)
 *
 * @mixin \Eloquent
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

    public static array $rules = [
        'user_id'       => 'required|integer',
        'provider'      => 'required|string',
        'token'         => 'required|string',
        'refresh_token' => 'required|string',
        'expires_at'    => 'nullable|datetime',
    ];

    public function isExpired(): Attribute
    {
        return Attribute::make(
            get: fn () => now()->isAfter($this->expires_at),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'user_id'       => 'integer',
            'provider'      => 'string',
            'token'         => 'string',
            'refresh_token' => 'string',
            'expires_at'    => 'datetime',
        ];
    }
}
