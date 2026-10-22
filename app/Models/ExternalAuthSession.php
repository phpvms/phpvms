<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int         $id
 * @property int         $user_id
 * @property string      $connection_id
 * @property string      $provider_user_id
 * @property string|null $oidc_sid
 * @property string      $session_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read OAuthConnection $connection
 * @property-read User $user
 *
 * @method static Builder<static>|ExternalAuthSession query()
 *
 * @mixin \Eloquent
 */
class ExternalAuthSession extends Model
{
    public $table = 'external_auth_sessions';

    protected $fillable = [
        'user_id',
        'connection_id',
        'provider_user_id',
        'oidc_sid',
        'session_id',
    ];

    public static array $rules = [
        'user_id'          => 'required|integer',
        'connection_id'    => 'required|string',
        'provider_user_id' => 'required|string',
        'oidc_sid'         => 'nullable|string',
        'session_id'       => 'required|string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(OAuthConnection::class, 'connection_id', 'connection_id');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
        ];
    }
}
