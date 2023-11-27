<?php

namespace App\Models;

use App\Contracts\Model;
use App\Models\Casts\FuelCast;
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
 * @property float    mtow
 * @property float    zfw
 * @property string   hex_code
 * @property Airport  airport
 * @property Airport  hub
 * @property Airport  home
 * @property Subfleet subfleet
 * @property int      status
 * @property int      state
 * @property Carbon   landing_time
 * @property float    fuel_onboard
 * @property Bid      bid
 */
class Aircraft extends Model
{
    use BelongsToThrough;
    use ExpensableTrait;
    use FilesTrait;
    use HasFactory;
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
        'landing_time',
        'flight_time',
        'mtow',
        'zfw',
        'fuel_onboard',
        'status',
        'state',
    ];

    /**
     * The attributes that should be casted to native types.
     */
    protected $casts = [
        'flight_time'  => 'float',
        'fuel_onboard' => FuelCast::class,
        'mtow'         => 'float',
        'state'        => 'integer',
        'subfleet_id'  => 'integer',
        'zfw'          => 'float',
    ];

    /**
     * Validation rules
     */
    public static $rules = [
        'fin'          => 'nullable',
        'mtow'         => 'nullable|numeric',
        'name'         => 'required',
        'registration' => 'required',
        'status'       => 'required',
        'subfleet_id'  => 'required',
        'zfw'          => 'nullable|numeric',
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
        'landing_time',
        'flight_time',
        'mtow',
        'zfw',
        'fuel_onboard',
        'status',
        'state',
    ];

    /**
     * @return Attribute
     */
    public function active(): Attribute
    {
        return Attribute::make(
            get: fn ($_, $attr) => $attr['status'] === AircraftStatus::ACTIVE
        );
    }

    /**
     * @return Attribute
     */
    public function icao(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => strtoupper($value)
        );
    }

    /**
     * @return Attribute
     */
    public function ident(): Attribute
    {
        return Attribute::make(
            get: fn ($_, $attrs) => $attrs['registration'].' ('.$attrs['icao'].')'
        );
    }

    /**
     * Return the landing time
     *
     * @return Attribute
     */
    public function landingTime(): Attribute
    {
        return Attribute::make(
            get: function ($_, $attrs) {
                if (array_key_exists('landing_time', $attrs) && filled($attrs['landing_time'])) {
                    return new Carbon($attrs['landing_time']);
                }

                return $attrs['landing_time'];
            }
        );
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
     *
     * @return HasOne
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
}
