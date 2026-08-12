<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property string     $static_id
 * @property int        $user_id
 * @property string     $flight_id
 * @property int        $aircraft_id
 * @property array|null $fare_data
 * @property Carbon     $expires_at
 */
class SimBriefAttempt extends Model
{
    public $table = 'simbrief_attempts';

    protected $primaryKey = 'static_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'static_id',
        'user_id',
        'flight_id',
        'aircraft_id',
        'fare_data',
        'expires_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function flight(): BelongsTo
    {
        return $this->belongsTo(Flight::class);
    }

    public function aircraft(): BelongsTo
    {
        return $this->belongsTo(Aircraft::class);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'aircraft_id' => 'integer',
            'fare_data'   => 'array',
            'user_id'     => 'integer',
            'expires_at'  => 'datetime',
        ];
    }
}
