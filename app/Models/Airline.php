<?php

namespace App\Models;

use App\Contracts\Model;
use App\Enums\JournalType;
use App\Traits\FilesTrait;
use App\Traits\JournalTrait;
use Database\Factories\AirlineFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Kyslik\ColumnSortable\Sortable;
use Override;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int         $id
 * @property string      $icao
 * @property string|null $iata
 * @property string      $name
 * @property string|null $callsign
 * @property string|null $country
 * @property string|null $logo
 * @property bool        $active
 * @property bool        $low_cost
 * @property int|null    $total_flights
 * @property int|null    $total_time
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Collection<int, Aircraft> $aircraft
 * @property-read int|null $aircraft_count
 * @property-read mixed $code
 * @property-read Collection<int, File> $files
 * @property-read int|null $files_count
 * @property-read Collection<int, Flight> $flights
 * @property-read int|null $flights_count
 * @property-read Journal|null $journal
 * @property-read Collection<int, Pirep> $pireps
 * @property-read int|null $pireps_count
 * @property-read Collection<int, Subfleet> $subfleets
 * @property-read int|null $subfleets_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static Builder<static>|Airline active()
 * @method static Builder<static>|Airline byIcao(string $icao)
 * @method static AirlineFactory          factory($count = null, $state = [])
 * @method static Builder<static>|Airline newModelQuery()
 * @method static Builder<static>|Airline newQuery()
 * @method static Builder<static>|Airline onlyTrashed()
 * @method static Builder<static>|Airline query()
 * @method static Builder<static>|Airline sortable($defaultParameters = null)
 * @method static Builder<static>|Airline whereActive($value)
 * @method static Builder<static>|Airline whereCallsign($value)
 * @method static Builder<static>|Airline whereCountry($value)
 * @method static Builder<static>|Airline whereCreatedAt($value)
 * @method static Builder<static>|Airline whereDeletedAt($value)
 * @method static Builder<static>|Airline whereIata($value)
 * @method static Builder<static>|Airline whereIcao($value)
 * @method static Builder<static>|Airline whereId($value)
 * @method static Builder<static>|Airline whereLogo($value)
 * @method static Builder<static>|Airline whereName($value)
 * @method static Builder<static>|Airline whereTotalFlights($value)
 * @method static Builder<static>|Airline whereTotalTime($value)
 * @method static Builder<static>|Airline whereUpdatedAt($value)
 * @method static Builder<static>|Airline withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Airline withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Airline extends Model
{
    use FilesTrait;
    use HasFactory;
    use JournalTrait;
    use LogsActivity;
    use SoftDeletes;
    use Sortable;

    private const array SELECT_LIST_ORDER_COLUMNS = [
        'id',
        'name',
        'icao',
        'iata',
    ];

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
        'low_cost',
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
                if ($this->iata) {
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

    /**
     * The attributes that should be casted to native types.
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'total_flights' => 'int',
            'total_time'    => 'int',
            'active'        => 'boolean',
            'low_cost'      => 'boolean',
        ];
    }

    /*
     * Query scopes
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    #[Scope]
    protected function byIcao(Builder $query, string $icao): void
    {
        $query->where('icao', strtoupper(trim($icao)));
    }

    /**
     * Return a list of airlines as `[id => name]` for use in form select boxes.
     *
     * Mirrors the previous AirlineRepository::selectBoxList contract.
     */
    public static function selectList(bool $addBlank = false, bool $onlyActive = true, string $orderBy = 'id'): array
    {
        $query = static::orderBy(self::sanitizeSelectListOrderBy($orderBy));
        if ($onlyActive) {
            $query->where('active', true);
        }

        $list = $query->pluck('name', 'id')->toArray();

        if ($addBlank) {
            return ['' => ''] + $list;
        }

        return $list;
    }

    private static function sanitizeSelectListOrderBy(string $orderBy): string
    {
        return in_array($orderBy, self::SELECT_LIST_ORDER_COLUMNS, true) ? $orderBy : 'id';
    }
}
