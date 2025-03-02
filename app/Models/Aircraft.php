<?php

namespace App\Models;

use App\Contracts\Model;
use App\Models\Casts\FuelCast;
use App\Models\Casts\MassCast;
use App\Models\Enums\AircraftStatus;
use App\Models\Traits\ExpensableTrait;
use App\Models\Traits\FilesTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kyslik\ColumnSortable\Sortable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Znck\Eloquent\Relations\BelongsToThrough as ZnckBelongsToThrough;
use Znck\Eloquent\Traits\BelongsToThrough;

/**
 * @property int      id
 * @property mixed    subfleet_id
 * @property string   airport_id   The apt where the aircraft is
 * @property string   hub_id       The apt where the aircraft is based
 * @property string   ident
 * @property string   name
 * @property string   icao
 * @property string   registration
 * @property string   fin
 * @property int      flight_time
 * @property Mass     dow
 * @property Mass     mlw
 * @property Mass     mtow
 * @property Mass     zfw
 * @property string   hex_code
 * @property string   selcal
 * @property Airport  airport
 * @property Airport  hub
 * @property Airport  home
 * @property Airline  airline
 * @property Subfleet subfleet
 * @property int      status
 * @property int      state
 * @property string   simbrief_type
 * @property Carbon   landing_time
 * @property Fuel     fuel_onboard
 * @property Bid      bid
 */
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

    /**
     * The attributes that should be casted to native types.
     */
    protected $casts = [
        'flight_time'  => 'float',
        'fuel_onboard' => FuelCast::class,
        'dow'          => MassCast::class,
        'mlw'          => MassCast::class,
        'mtow'         => MassCast::class,
        'state'        => 'integer',
        'subfleet_id'  => 'integer',
        'zfw'          => MassCast::class,
    ];

    /**
     * Validation rules
     */
    public static $rules = [
        'name'          => 'required',
        'registration'  => 'required',
        'fin'           => 'nullable',
        'selcal'        => 'nullable',
        'status'        => 'required',
        'subfleet_id'   => 'required',
        'dow'           => 'nullable|numeric',
        'zfw'           => 'nullable|numeric',
        'mtow'          => 'nullable|numeric',
        'mlw'           => 'nullable|numeric',
        'simbrief_type' => 'nullable',
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
            ->dontSubmitEmptyLogs();
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

    public function home(): HasOne
    {
        return $this->hasOne(Airport::class, 'id', 'hub_id');
    }

    /**
     * Use home()
     *
     * @deprecated
     */
    public function hub(): HasOne
    {
        return $this->hasOne(Airport::class, 'id', 'hub_id');
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
}
