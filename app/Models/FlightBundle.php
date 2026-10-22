<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Model;
use App\Enums\BundleType;
use App\Observers\BundleObserver;
use App\Traits\HasAssets;
use Database\Factories\FlightBundleFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Override;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int         $id
 * @property string      $name
 * @property string|null $description
 * @property BundleType  $type
 * @property bool        $enabled
 * @property bool        $visible
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property int|null    $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Flight> $flights
 * @property-read Collection<int, Subfleet> $subfleets
 * @property-read User|null $creator
 * @property-read bool $has_dates
 * @property-read string|null $image_url
 * @property int|null $enabled_flights_count
 * @property int|null $disabled_flights_count
 */
#[ObservedBy(BundleObserver::class)]
class FlightBundle extends Model
{
    use HasAssets;

    /** @use HasFactory<FlightBundleFactory> */
    use HasFactory;

    use LogsActivity;
    use SoftDeletes;

    public $table = 'flight_bundles';

    /**
     * Mirrors the column default, so a bundle read back before it has been
     * refreshed still answers `type` rather than null.
     */
    protected $attributes = [
        'type' => BundleType::Flights->value,
    ];

    protected $fillable = [
        'name',
        'description',
        'type',
        'enabled',
        'visible',
        'start_date',
        'end_date',
        'created_by',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** @return HasMany<Flight, $this> */
    public function flights(): HasMany
    {
        return $this->hasMany(Flight::class, 'bundle_id');
    }

    /**
     * Default subfleets for this bundle's flights. A flight inherits these only
     * when it has no `flight_subfleet` pins of its own — see
     * Flight::accessibleSubfleetsFor().
     */
    public function subfleets(): BelongsToMany
    {
        return $this->belongsToMany(Subfleet::class, 'bundle_subfleet', 'bundle_id', 'subfleet_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Same relation as the LogsActivity trait's, typed. The trait resolves the
     * model through `determineActivityModel()`, which returns a bare `string`,
     * so callers see `Model` and lose Activity's own accessors (`changes()`,
     * `properties()`). Naming the class directly is what `config/activitylog.php`
     * already pins `activity_model` to.
     *
     * @return MorphMany<Activity, $this>
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    #[Scope]
    protected function visible(Builder $query): Builder
    {
        return $query->where('visible', true);
    }

    /** The bundle's hero image lives in the `flight-bundle` slot, keyed on the bundle id. */
    public function assetSlot(): string
    {
        return Asset::SLOT_BUNDLE;
    }

    /**
     * A browser-loadable URL for the bundle's hero image, or null.
     *
     * A per-row lookup with an explicit string key, via {@see HasAssets::assetUrl()}
     * — NOT a hasOne on the integer id: `assets.key` is varchar, and Postgres
     * refuses `key IN (38, 39)` with integer bindings.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->assetUrl(),
        );
    }

    /**
     * True when this bundle has any schedule window set (start_date or end_date
     * is non-null). Drives FlightForm's "bundle owns schedule" UI branch and
     * SetVisibleFlights' case-B/case-C dispatch.
     */
    protected function hasDates(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => filled($this->start_date) || filled($this->end_date),
        );
    }

    /**
     * This bundle's flights in leg order, plus the first thing wrong with that
     * order. A tour needs `route_leg` to run 1..N over its flights with no gap
     * and no repeat; a `flights` bundle has no such requirement and callers
     * simply do not ask.
     *
     * `problem` names the reason the sequence is unusable and `leg` the number
     * it concerns, rather than a finished sentence, so each caller phrases its
     * own: the bundle page warns, the tour path refuses to start. Both are null
     * exactly when `valid` is true.
     *
     * A flight carrying no `route_leg` surfaces as a missing leg rather than as
     * a problem of its own — Flight::routeLeg() canonicalizes '', '0' and 0 to
     * NULL as its deliberate "absent" sentinel, so there is no leg 0 for an
     * unnumbered flight to be confused with, and numbering starts at 1.
     *
     * @return array{
     *     flights: Collection<int, Flight>,
     *     valid: bool,
     *     problem: 'empty'|'missing'|'duplicate'|null,
     *     leg: int|null,
     * }
     */
    public function tourLegSequence(): array
    {
        $flights = $this->flights()->orderBy('route_leg')->get();

        $problem = null;
        $leg = null;

        if ($flights->isEmpty()) {
            // An empty tour has nothing to bid, so it is not merely "trivially
            // contiguous" — it is unusable, and callers need to hear that.
            $problem = 'empty';
        } else {
            // Unnumbered flights land under 0, which no expected leg matches,
            // so they read as whichever leg they failed to fill.
            $counts = $flights->countBy(fn (Flight $flight): int => $flight->route_leg ?? 0);

            foreach (range(1, $flights->count()) as $expected) {
                $count = $counts->get($expected, 0);

                if ($count !== 1) {
                    $problem = $count === 0 ? 'missing' : 'duplicate';
                    $leg = $expected;

                    break;
                }
            }
        }

        return [
            'flights' => $flights,
            'valid'   => $problem === null,
            'problem' => $problem,
            'leg'     => $leg,
        ];
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'type'       => BundleType::class,
            'enabled'    => 'boolean',
            'visible'    => 'boolean',
            'start_date' => 'datetime',
            'end_date'   => 'datetime',
        ];
    }
}
