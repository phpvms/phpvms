<?php

namespace App\Models;

use App\Contracts\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property string $email
 * @property string $token
 * @property int    $usage_count
 * @property int    $usage_limit
 * @property Carbon $expires_at
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
