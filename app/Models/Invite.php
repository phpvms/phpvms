<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property int                             $id
 * @property string|null                     $email
 * @property string                          $token
 * @property int                             $usage_count
 * @property int|null                        $usage_limit
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $link
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invite newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invite newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invite query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invite whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invite whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invite whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invite whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invite whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invite whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invite whereUsageCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invite whereUsageLimit($value)
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
            get: fn ($value, $attrs) => url('/register?invite='.$attrs['id'].'&token='.$attrs['token'])
        );
    }

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
