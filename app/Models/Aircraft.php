<?php

namespace App\Models;

use App\Casts\FuelCast;
use App\Casts\MassCast;
use App\Contracts\Model;
use App\Models\Enums\AircraftStatus;
use App\Observers\AircraftObserver;
use App\Traits\ExpensableTrait;
use App\Traits\FilesTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kyslik\ColumnSortable\Sortable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Znck\Eloquent\Relations\BelongsToThrough as ZnckBelongsToThrough;
use Znck\Eloquent\Traits\BelongsToThrough;

/**
 * @property int                             $id
 * @property int                             $subfleet_id
 * @property string|null                     $icao
 * @property string|null                     $iata
 * @property string|null                     $airport_id
 * @property string|null                     $hub_id
 * @property string|null                     $landing_time
 * @property string                          $name
 * @property string|null                     $registration
 * @property string|null                     $fin
 * @property string|null                     $hex_code
 * @property string|null                     $selcal
 * @property mixed|null                      $dow
 * @property mixed|null                      $mtow
 * @property mixed|null                      $mlw
 * @property mixed|null                      $zfw
 * @property string|null                     $simbrief_type
 * @property mixed|null                      $fuel_onboard
 * @property float|null                      $flight_time
 * @property string                          $status
 * @property int                             $state
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read mixed $active
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Airport|null $airport
 * @property-read Bid|null $bid
 * @property-read Collection<int, Expense> $expenses
 * @property-read int|null $expenses_count
 * @property-read Collection<int, File> $files
 * @property-read int|null $files_count
 * @property-read Airport|null $home
 * @property-read Airport|null $hub
 * @property-read mixed $ident
 * @property-read Collection<int, Pirep> $pireps
 * @property-read int|null $pireps_count
 * @property-read SimBriefAircraft|null $sbaircraft
 * @property-read Collection<int, SimBriefAirframe> $sbairframes
 * @property-read int|null $sbairframes_count
 * @property-read Collection<int, SimBrief> $simbriefs
 * @property-read int|null $simbriefs_count
 * @property-read Subfleet|null $subfleet
 * @property-read Airline|null $airline
 *
 * @method static \Database\Factories\AircraftFactory                    factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft sortable($defaultParameters = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereAirportId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereDow($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereFin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereFlightTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereFuelOnboard($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereHexCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereHubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereIata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereIcao($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereLandingTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereMlw($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereMtow($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereRegistration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereSelcal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereSimbriefType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereSubfleetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft whereZfw($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Aircraft withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[ObservedBy(AircraftObserver::class)]
class Aircraft extends Model
{
    use BelongsToThrough;
    use ExpensableTrait;
    use FilesTrait;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;
    use Sortable;

    public $table = 'aircraft';

    protected $fillable = [
        'subfleet_id',
        'airport_id',
        'hub_id',
        'iata',
        'icao',
        'name',
        'registration',
        'fin',
        'hex_code',
        'selcal',
        'landing_time',
        'flight_time',
        'dow',
        'mlw',
        'mtow',
        'zfw',
        'fuel_onboard',
        'status',
        'state',
        'simbrief_type',
    ];

    public $sortable = [
        'subfleet_id',
        'airport_id',
        'hub_id',
        'iata',
        'icao',
        'name',
        'registration',
        'fin',
        'hex_code',
        'selcal',
        'landing_time',
        'flight_time',
        'dow',
        'mtow',
        'mlw',
        'zfw',
        'fuel_onboard',
        'simbrief_type',
        'status',
        'state',
    ];

    public function active(): Attribute
    {
        return Attribute::make(
            get: fn ($_, $attr) => $attr['status'] === AircraftStatus::ACTIVE
        );
    }

    public function icao(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => strtoupper($value)
        );
    }

    public function ident(): Attribute
    {
        return Attribute::make(
            get: fn ($_, $attrs) => $attrs['registration'].' ('.$attrs['icao'].')'
        );
    }

    /**
     * Return the landing time
     */
    public function landingTime(): Attribute
    {
        return Attribute::make(
            get: function ($_, $attrs) {
                if (!array_key_exists('landing_time', $attrs)) {
                    return null;
                }

                if (filled($attrs['landing_time'])) {
                    return new Carbon($attrs['landing_time']);
                }

                return $attrs['landing_time'];
            }
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            // Bypass custom casts to log only internal DB changes (internal unit)
            ->useAttributeRawValues([
                'fuel_onboard',
                'dow',
                'mlw',
                'mtow',
                'zfw',
            ]);
    }

    /**
     * Relationships
     */
    public function airline(): ZnckBelongsToThrough
    {
        return $this->belongsToThrough(Airline::class, Subfleet::class);
    }

    public function airport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'airport_id');
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class, 'id', 'aircraft_id');
    }

    public function home(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'hub_id');
    }

    /**
     * Use home()
     *
     * @deprecated
     */
    public function hub(): BelongsTo
    {
        return $this->home();
    }

    public function pireps(): HasMany
    {
        return $this->hasMany(Pirep::class, 'aircraft_id');
    }

    public function simbriefs(): HasMany
    {
        return $this->hasMany(SimBrief::class, 'aircraft_id');
    }

    public function subfleet(): BelongsTo
    {
        return $this->belongsTo(Subfleet::class, 'subfleet_id');
    }

    public function sbaircraft(): HasOne
    {
        return $this->hasOne(SimBriefAircraft::class, 'icao', 'icao');
    }

    public function sbairframes(): HasMany
    {
        return $this->hasMany(SimBriefAirframe::class, 'icao', 'icao');
    }

    /**
     * The attributes that should be casted to native types.
     */
    protected function casts(): array
    {
        return [
            'flight_time'  => 'float',
            'fuel_onboard' => FuelCast::class,
            'dow'          => MassCast::class,
            'mlw'          => MassCast::class,
            'mtow'         => MassCast::class,
            'state'        => 'integer',
            'subfleet_id'  => 'integer',
            'zfw'          => MassCast::class,
        ];
    }
}
