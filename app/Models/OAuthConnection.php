<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int               $id
 * @property string            $connection_id
 * @property string            $display_name
 * @property string            $provider
 * @property null|string       $client_id
 * @property null|string       $client_secret
 * @property null|list<string> $scopes
 * @property null|array        $configuration
 * @property bool              $enabled
 * @property bool              $login_enabled
 * @property bool              $registration_enabled
 * @property bool              $linking_enabled
 * @property null|string       $managed_by
 * @property int               $sort_order
 * @property Carbon|null       $created_at
 * @property Carbon|null       $updated_at
 * @property-read Collection<int, ExternalAuthSession> $external_auth_sessions
 * @property-read Collection<int, UserIdentity> $identities
 * @property-read Collection<int, UserOAuthToken> $tokens
 *
 * @method static Builder<static>|OAuthConnection query()
 *
 * @mixin \Eloquent
 */
class OAuthConnection extends Model
{
    public $table = 'oauth_connections';

    protected $fillable = [
        'connection_id',
        'display_name',
        'provider',
        'client_id',
        'client_secret',
        'scopes',
        'configuration',
        'enabled',
        'login_enabled',
        'registration_enabled',
        'linking_enabled',
        'managed_by',
        'sort_order',
    ];

    public function identities(): HasMany
    {
        return $this->hasMany(UserIdentity::class, 'connection_id', 'connection_id');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(UserOAuthToken::class, 'connection_id', 'connection_id');
    }

    public function external_auth_sessions(): HasMany
    {
        return $this->hasMany(ExternalAuthSession::class, 'connection_id', 'connection_id');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'client_secret'        => 'encrypted',
            'scopes'               => 'array',
            'configuration'        => 'encrypted:array',
            'enabled'              => 'boolean',
            'login_enabled'        => 'boolean',
            'registration_enabled' => 'boolean',
            'linking_enabled'      => 'boolean',
            'sort_order'           => 'integer',
        ];
    }
}
