<?php

namespace App\Models;

use App\Contracts\Model;
use App\Features\Tour\Models\UserTour;
use Database\Factories\BidFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int         $id
 * @property int         $user_id
 * @property string      $flight_id
 * @property int|null    $aircraft_id
 * @property string|null $user_tour_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Aircraft|null $aircraft
 * @property-read Flight|null $flight
 * @property-read User|null $user
 * @property-read UserTour|null $userTour
 *
 * @method static BidFactory          factory($count = null, $state = [])
 * @method static Builder<static>|Bid newModelQuery()
 * @method static Builder<static>|Bid newQuery()
 * @method static Builder<static>|Bid query()
 * @method static Builder<static>|Bid whereAircraftId($value)
 * @method static Builder<static>|Bid whereCreatedAt($value)
 * @method static Builder<static>|Bid whereFlightId($value)
 * @method static Builder<static>|Bid whereId($value)
 * @method static Builder<static>|Bid whereUpdatedAt($value)
 * @method static Builder<static>|Bid whereUserId($value)
 *
 * @mixin \Eloquent
 */
class Bid extends Model
{
    /** @use HasFactory<BidFactory> */
    use HasFactory;

    public $table = 'bids';

    protected $fillable = [
        'user_id',
        'flight_id',
        'aircraft_id',
        'user_tour_id',
    ];

    /**
     * Relationships
     */
    public function aircraft(): BelongsTo
    {
        return $this->belongsTo(Aircraft::class, 'aircraft_id');
    }

    public function flight(): BelongsTo
    {
        return $this->belongsTo(Flight::class, 'flight_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The tour run this bid belongs to, or null for an ordinary bid.
     *
     * Set only by the tour path, which creates every leg's bid at once; it is
     * what makes cancelling or expiring a whole run one statement.
     */
    public function userTour(): BelongsTo
    {
        return $this->belongsTo(UserTour::class, 'user_tour_id');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'user_id'     => 'integer',
            'aircraft_id' => 'integer',
        ];
    }
}
