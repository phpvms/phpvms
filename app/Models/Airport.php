<?php

namespace App\Models;

use App\Contracts\Model;
use App\Models\Traits\ExpensableTrait;
use App\Models\Traits\FilesTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kyslik\ColumnSortable\Sortable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class Airport
 *
 * @property string id
 * @property string iata
 * @property string icao
 * @property string name
 * @property string full_name
 * @property string description
 * @property string location
 * @property string region
 * @property string country
 * @property string timezone
 * @property string notes
 * @property float  ground_handling_cost
 * @property float  fuel_100ll_cost
 * @property float  fuel_jeta_cost
 * @property float  fuel_mogas_cost
 * @property float  lat
 * @property float  lon
 * @property int    elevation
 * @property bool   hub
 */
class Airport extends Model
{
    use ExpensableTrait;
    use FilesTrait;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;
    use Sortable;

    public $table = 'airports';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'iata',
        'icao',
        'name',
        'location',
        'region',
        'country',
        'lat',
        'lon',
        'elevation',
        'hub',
        'timezone',
        'tz',
        'ground_handling_cost',
        'fuel_100ll_cost',
        'fuel_jeta_cost',
        'fuel_mogas_cost',
        'notes',
    ];

    /**
     * Validation rules
     */
    public static $rules = [
        'icao'                 => 'required',
        'iata'                 => 'sometimes|nullable',
        'name'                 => 'required',
        'location'             => 'sometimes',
        'region'               => 'sometimes',
        'country'              => 'sometimes',
        'lat'                  => 'required|numeric',
        'lon'                  => 'required|numeric',
        'elevation'            => 'nullable|numeric',
        'ground_handling_cost' => 'nullable|numeric',
        'fuel_100ll_cost'      => 'nullable|numeric',
        'fuel_jeta_cost'       => 'nullable|numeric',
        'fuel_mogas_cost'      => 'nullable|numeric',
    ];

    public $sortable = [
        'id',
        'iata',
        'icao',
        'name',
        'hub',
        'notes',
        'elevation',
        'location',
        'region',
        'country',
    ];

    /**
     * Capitalize the ICAO
     */
    public function icao(): Attribute
    {
        return Attribute::make(
            set: fn ($icao) => [
                'id'   => strtoupper($icao),
                'icao' => strtoupper($icao),
            ]
        );
    }

    /**
     * Capitalize the IATA code
     */
    public function iata(): Attribute
    {
        return Attribute::make(
            set: fn ($iata) => strtoupper($iata)
        );
    }

    /**
     * Return full name like:
     * KJFK - John F Kennedy
     *
     * @return string
     */
    public function fullName(): Attribute
    {
        return Attribute::make(
            get: fn ($_, $attrs) => $this->icao.' - '.$this->name
        );
    }

    /**
     * Return full description like:
     * KJFK/JFK - John F Kennedy (hub)
     */
    public function description(): Attribute
    {
        return Attribute::make(
            get: fn ($_, $attrs) => $attrs['icao']
                .(empty($attrs['iata']) ? '' : '/'.$attrs['iata'])
                .' - '.$attrs['name']
                .($attrs['hub'] ? ' (hub)' : '')
        );
    }

    /**
     * Shortcut for timezone
     */
    public function tz(): Attribute
    {
        return Attribute::make(
            get: fn ($_, $attrs) => $attrs['timezone'],
            set: fn ($value) => [
                'timezone' => $value,
            ]
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
    public function departures(): HasMany
    {
        return $this->hasMany(Flight::class, 'dpt_airport_id');
    }

    public function arrivals(): HasMany
    {
        return $this->hasMany(Flight::class, 'arr_airport_id');
    }

    public function aircraft(): HasMany
    {
        return $this->hasMany(Aircraft::class, 'airport_id');
    }

    public function pilots(): HasMany
    {
        // Users currently at this airport
        return $this->hasMany(User::class, 'curr_airport_id');
    }

    public function users(): HasMany
    {
        // Users based at this airport
        return $this->hasMany(User::class, 'home_airport_id');
    }
    protected function casts(): array
    {
        return [
            'lat'                  => 'float',
            'lon'                  => 'float',
            'hub'                  => 'boolean',
            'ground_handling_cost' => 'float',
            'fuel_100ll_cost'      => 'float',
            'fuel_jeta_cost'       => 'float',
            'fuel_mogas_cost'      => 'float',
        ];
    }
}
