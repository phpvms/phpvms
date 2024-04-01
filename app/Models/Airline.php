<?php

namespace App\Models;

use App\Contracts\Model;
use App\Models\Enums\JournalType;
use App\Models\Traits\FilesTrait;
use App\Models\Traits\JournalTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Kyslik\ColumnSortable\Sortable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property mixed   id
 * @property string  code
 * @property string  icao
 * @property string  iata
 * @property string  name
 * @property string  callsign
 * @property string  logo
 * @property string  country
 * @property Journal journal
 */
class Airline extends Model
{
    use FilesTrait;
    use HasFactory;
    use JournalTrait;
    use SoftDeletes;
    use Sortable;
    use LogsActivity;

    public $table = 'airlines';

    /**
     * The journal type for the callback
     */
    public $journal_type = JournalType::AIRLINE;

    protected $fillable = [
        'icao',
        'iata',
        'name',
        'callsign',
        'logo',
        'country',
        'total_flights',
        'total_time',
        'active',
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'total_flights' => 'int',
        'total_time'    => 'int',
        'active'        => 'boolean',
    ];

    /**
     * Validation rules
     *
     * @var array
     */
    public static $rules = [
        'callsign' => 'nullable',
        'country'  => 'nullable',
        'iata'     => 'nullable|max:5',
        'icao'     => 'required|max:5',
        'logo'     => 'nullable',
        'name'     => 'required',
    ];

    public $sortable = [
        'id',
        'name',
        'icao',
        'iata',
        'country',
        'callsign',
    ];

    /**
     * For backwards compatibility
     */
    public function code(): Attribute
    {
        return Attribute::make(
            get: function ($_, $attrs) {
                if ($this->iata && $this->iata !== '') {
                    return $this->iata;
                }

                return $this->icao;
            }
        );
    }

    /**
     * Capitalize the IATA code when set
     */
    public function iata(): Attribute
    {
        return Attribute::make(
            set: fn ($iata) => Str::upper($iata)
        );
    }

    /**
     * Capitalize the ICAO when set
     */
    public function icao(): Attribute
    {
        return Attribute::make(
            set: fn ($icao) => Str::upper($icao)
        );
    }

    /*
     * Relationships
     */
    public function subfleets(): HasMany
    {
        return $this->hasMany(Subfleet::class, 'airline_id', 'id');
    }

    public function aircraft(): HasManyThrough
    {
        return $this->hasManyThrough(Aircraft::class, Subfleet::class, 'airline_id', 'subfleet_id', 'id', 'id');
    }

    public function flights(): HasMany
    {
        return $this->hasMany(Flight::class, 'airline_id', 'id');
    }

    public function pireps(): HasMany
    {
        return $this->HasMany(Pirep::class, 'airline_id', 'id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'airline_id', 'id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
