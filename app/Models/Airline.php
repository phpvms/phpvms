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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
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
 * @property string|null $logo          external URL only; a hosted mark is an Asset
 * @property string|null $logo_hash     accessor over the asset's change stamp
 * @property Asset|null  $logoAsset
 * @property string|null $logo_url
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

    /** @use HasFactory<AirlineFactory> */
    use HasFactory;

    use JournalTrait;
    use LogsActivity;
    use SoftDeletes;
    use Sortable;

    /**
     * Directory on the public files disk that uploaded logos live in. A logo
     * value under this prefix is one we host; anything else is an external URL.
     */
    public const string LOGO_DIRECTORY = 'airlines';

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
     * The mark loads with the airline.
     *
     * `logo_url` is read almost everywhere an airline is — the API resource,
     * the Inertia identity props, flight lists, the shipped themes and any
     * module serving airlines — and the app runs with lazy loading prevented, so an
     * accessor reaching for an unloaded relation is a hard error rather than a
     * quiet N+1. Eager-loading it here fixes every one of those call sites at
     * once, and Eloquent batches it into a single extra query per collection.
     *
     * @var list<string>
     */
    protected $with = ['logoAsset'];

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
     * The airline's mark, when we host it. One asset in the `airline-logo`
     * slot, public like the rest of the mark's rendering surface.
     *
     * Keyed on the ICAO exactly as stored (uppercase), so this is a plain
     * exact-match relation the caller can eager-load — `Airline::with('logoAsset')`
     * — rather than a per-row lookup that turns any airline list into an N+1.
     * `airlines.icao` is unique (`create_phpvms_table.php:173`), so the key is
     * unique within the slot too.
     */
    public function logoAsset(): HasOne
    {
        return $this->hasOne(Asset::class, 'key', 'icao')
            ->where('slot', Asset::SLOT_AIRLINE_LOGO);
    }

    /**
     * A URL that is safe to render.
     *
     * A hosted logo is an asset now. The `logo` column keeps only the other
     * case: an absolute URL someone typed in or imported, which is not a file
     * we host and so cannot become an asset.
     */
    public function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn ($_, array $attrs): ?string => $this->logoAsset?->url()
                ?? self::resolveExternalLogoUrl($attrs['logo'] ?? null)
        );
    }

    /**
     * Cache-busting token for the logo, or null when we do not host it.
     *
     * Reads through to the asset's change stamp, which is a crc32b of the file
     * contents — the same algorithm the retired `logo_hash` column used, so
     * anything comparing this across the upgrade sees the same shape.
     */
    public function logoHash(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->logoAsset?->last_update
        );
    }

    /**
     * Resolve a raw column value to a URL that is safe to render, so the admin
     * preview and the accessor cannot drift apart.
     *
     * Only the external case reaches here now, but it still has to resolve a
     * legacy `airlines/…` path: an install whose data migration could not adopt
     * a file (it had been deleted) keeps the column value, and rendering
     * nothing at all would be worse than rendering the old file if it turns up.
     */
    public static function resolveExternalLogoUrl(?string $logo): ?string
    {
        if (blank($logo)) {
            return null;
        }

        if (self::isUploadedLogo($logo)) {
            return Storage::disk(config('filesystems.public_files'))->url($logo);
        }

        // Anything typed into the URL field lands in an <img src>, so only let
        // through schemes that cannot execute. Relative and protocol-relative
        // paths have no scheme at all and stay allowed.
        $scheme = parse_url($logo, PHP_URL_SCHEME);

        if ($scheme !== null && !in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
            return null;
        }

        return $logo;
    }

    /**
     * Whether the logo value is a path on our own disk rather than a URL.
     */
    public static function isUploadedLogo(string $logo): bool
    {
        return str_starts_with($logo, self::LOGO_DIRECTORY.'/');
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
