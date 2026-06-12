<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int         $id
 * @property int         $user_id
 * @property string      $provider
 * @property string      $token
 * @property string      $refresh_token
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read bool $is_expired
 * @property-read User|null $user
 *
 * @method static Builder<static>|UserOAuthToken newModelQuery()
 * @method static Builder<static>|UserOAuthToken newQuery()
 * @method static Builder<static>|UserOAuthToken query()
 * @method static Builder<static>|UserOAuthToken whereCreatedAt($value)
 * @method static Builder<static>|UserOAuthToken whereExpiresAt($value)
 * @method static Builder<static>|UserOAuthToken whereId($value)
 * @method static Builder<static>|UserOAuthToken whereProvider($value)
 * @method static Builder<static>|UserOAuthToken whereRefreshToken($value)
 * @method static Builder<static>|UserOAuthToken whereToken($value)
 * @method static Builder<static>|UserOAuthToken whereUpdatedAt($value)
 * @method static Builder<static>|UserOAuthToken whereUserId($value)
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
            get: fn (): bool => now()->isAfter($this->expires_at),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    #[Override]
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
