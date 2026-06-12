<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int         $id
 * @property string|null $email
 * @property string      $token
 * @property int         $usage_count
 * @property int|null    $usage_limit
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $link
 *
 * @method static Builder<static>|Invite newModelQuery()
 * @method static Builder<static>|Invite newQuery()
 * @method static Builder<static>|Invite query()
 * @method static Builder<static>|Invite whereCreatedAt($value)
 * @method static Builder<static>|Invite whereEmail($value)
 * @method static Builder<static>|Invite whereExpiresAt($value)
 * @method static Builder<static>|Invite whereId($value)
 * @method static Builder<static>|Invite whereToken($value)
 * @method static Builder<static>|Invite whereUpdatedAt($value)
 * @method static Builder<static>|Invite whereUsageCount($value)
 * @method static Builder<static>|Invite whereUsageLimit($value)
 *
 * @mixin \Eloquent
 */
class Invite extends Model
{
    public $table = 'invites';

    protected $fillable = [
        'email',
        'token',
        'usage_count',
        'usage_limit',
        'expires_at',
    ];

    public static array $rules = [
        'email'       => 'nullable|string',
        'token'       => 'required|string',
        'usage_count' => 'integer',
        'usage_limit' => 'nullable|integer',
        'expires_at'  => 'nullable|datetime',
    ];

    public function link(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attrs): string => url('/register?invite='.$attrs['id'].'&token='.$attrs['token'])
        );
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'email'       => 'string',
            'token'       => 'string',
            'usage_count' => 'integer',
            'usage_limit' => 'integer',
            'expires_at'  => 'datetime',
        ];
    }
}
