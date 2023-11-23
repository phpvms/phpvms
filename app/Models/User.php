<?php

namespace App\Models;

use App\Models\Enums\JournalType;
use App\Models\Traits\JournalTrait;
use BezhanSalleh\FilamentShield\Support\Utils;
use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Kyslik\ColumnSortable\Sortable;
use Spatie\Permission\Traits\HasRoles;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

/**
 * @property int              id
 * @property int              pilot_id
 * @property int              airline_id
 * @property string           callsign
 * @property string           name
 * @property string           name_private Only first name, rest are initials
 * @property string           email
 * @property string           password
 * @property string           api_key
 * @property mixed            timezone
 * @property string           ident
 * @property string           atc
 * @property string           curr_airport_id
 * @property string           home_airport_id
 * @property string           avatar
 * @property Airline          airline
 * @property int              flights
 * @property int              flight_time
 * @property int              transfer_time
 * @property string           remember_token
 * @property \Carbon\Carbon   created_at
 * @property \Carbon\Carbon   updated_at
 * @property Rank             rank
 * @property Journal          journal
 * @property int              rank_id
 * @property string           discord_id
 * @property int              state
 * @property string           last_ip
 * @property \Carbon\Carbon   lastlogin_at
 * @property bool             opt_in
 * @property Pirep[]          pireps
 * @property string           last_pirep_id
 * @property Pirep            last_pirep
 * @property UserFieldValue[] fields
 * @property Role[]           roles
 * @property Subfleet[]       subfleets
 * @property TypeRating[]     typeratings
 * @property Airport          home_airport
 * @property Airport          current_airport
 * @property Airport          location
 * @property Bid[]            bids
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 * @mixin \Illuminate\Notifications\Notifiable
 */
class User extends Authenticatable implements MustVerifyEmail, FilamentUser
{
    use HasFactory;
    use HasRelationships;
    use HasRoles;
    use JournalTrait;
    use Notifiable;
    use SoftDeletes;
    use Sortable;

    public $table = 'users';

    /**
     * The journal type for when it's being created
     */
    public $journal_type = JournalType::USER;

    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'pilot_id',
        'callsign',
        'airline_id',
        'rank_id',
        'discord_id',
        'discord_private_channel_id',
        'api_key',
        'country',
        'home_airport_id',
        'curr_airport_id',
        'last_pirep_id',
        'flights',
        'flight_time',
        'transfer_time',
        'avatar',
        'timezone',
        'state',
        'status',
        'toc_accepted',
        'opt_in',
        'last_ip',
        'lastlogin_at',
        'notes',
        'created_at',
        'updated_at',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'api_key',
        'email',
        'name',
        'discord_id',
        'discord_private_channel_id',
        'password',
        'last_ip',
        'remember_token',
        'notes',
    ];

    protected $casts = [
        'id'                => 'integer',
        'pilot_id'          => 'integer',
        'flights'           => 'integer',
        'flight_time'       => 'integer',
        'transfer_time'     => 'integer',
        'balance'           => 'double',
        'state'             => 'integer',
        'status'            => 'integer',
        'toc_accepted'      => 'boolean',
        'opt_in'            => 'boolean',
        'lastlogin_at'      => 'datetime',
        'deleted_at'        => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    public static $rules = [
        'name'     => 'required',
        'email'    => 'required|email',
        'pilot_id' => 'required|integer',
        'callsign' => 'nullable|max:4',
    ];

    public $sortable = [
        'id',
        'name',
        'pilot_id',
        'callsign',
        'country',
        'airline_id',
        'rank_id',
        'home_airport_id',
        'curr_airport_id',
        'flights',
        'flight_time',
        'transfer_time',
        'created_at',
    ];

    /**
     * Format the pilot ID/ident
     *
     * @return Attribute
     */
    public function ident(): Attribute
    {
        return Attribute::make(
            get: function ($_, $attrs) {
                $length = setting('pilots.id_length');
                $ident_code = filled(setting('pilots.id_code')) ? setting(
                    'pilots.id_code'
                ) : optional($this->airline)->icao;

                return $ident_code.str_pad($attrs['pilot_id'], $length, '0', STR_PAD_LEFT);
            }
        );
    }

    /**
     * Format the pilot atc callsign, either return alphanumeric callsign or ident
     *
     * @return Attribute
     */
    public function atc(): Attribute
    {
        return Attribute::make(
            get: function ($_, $attrs) {
                $ident_code = filled(setting('pilots.id_code')) ? setting('pilots.id_code') : optional($this->airline)->icao;
                $atc = filled($attrs['callsign']) ? $ident_code.$attrs['callsign'] : $ident_code.$attrs['pilot_id'];

                return $atc;
            }
        );
    }

    /**
     * Return a "privatized" version of someones name - First and middle names full, last name initials
     *
     * @return Attribute
     */
    public function namePrivate(): Attribute
    {
        return Attribute::make(
            get: function ($_, $attrs) {
                $name_parts = explode(' ', $attrs['name']);
                $count = count($name_parts);
                if ($count === 1) {
                    return $name_parts[0];
                }

                $gdpr_name = '';
                $last_name = $name_parts[$count - 1];
                $loop_count = 0;

                while ($loop_count < ($count - 1)) {
                    $gdpr_name .= $name_parts[$loop_count].' ';
                    $loop_count++;
                }

                $gdpr_name .= mb_substr($last_name, 0, 1);

                return mb_convert_case($gdpr_name, MB_CASE_TITLE);
            }
        );
    }

    /**
     * Shortcut for timezone
     *
     * @return Attribute
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

    /**
     * Return a File model
     */
    public function avatar(): Attribute
    {
        return Attribute::make(
            get: function ($_, $attrs) {
                if (!$attrs['avatar']) {
                    return null;
                }

                return new File([
                    'path' => $attrs['avatar'],
                ]);
            }
        );
    }

    /**
     * @param mixed $size Size of the gravatar, in pixels
     *
     * @return string
     */
    public function gravatar($size = null)
    {
        $default = config('gravatar.default');

        $uri = config('gravatar.url').md5(strtolower(trim($this->email))).'?d='.urlencode($default);

        if ($size !== null) {
            $uri .= '&s='.$size;
        }

        return $uri;
    }

    public function resolveAvatarUrl()
    {
        /** @var File $avatar */
        $avatar = $this->avatar;
        if (empty($avatar)) {
            return $this->gravatar();
        }

        return $avatar->url;
    }

    /**
     * Relationships
     */
    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class, 'airline_id')->withTrashed();
    }

    public function awards(): BelongsToMany
    {
        return $this->belongsToMany(Award::class, 'user_awards')->withTrashed();
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class, 'user_id');
    }

    public function home_airport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'home_airport_id')->withTrashed();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'curr_airport_id')->withTrashed();
    }

    public function current_airport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'curr_airport_id')->withTrashed();
    }

    public function last_pirep(): BelongsTo
    {
        return $this->belongsTo(Pirep::class, 'last_pirep_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(UserFieldValue::class, 'user_id');
    }

    public function pireps(): HasMany
    {
        return $this->hasMany(Pirep::class, 'user_id');
    }

    public function rank(): BelongsTo
    {
        return $this->belongsTo(Rank::class, 'rank_id')->withTrashed();
    }

    public function typeratings(): BelongsToMany
    {
        return $this->belongsToMany(Typerating::class, 'typerating_user', 'user_id', 'typerating_id');
    }

    public function rated_subfleets()
    {
        return $this->hasManyDeep(Subfleet::class, ['typerating_user', Typerating::class, 'typerating_subfleet']);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // For the admin panel
        if ($panel->getId() === 'admin') {
            return $this->hasRole(Utils::getSuperAdminName()) || $this->can('page_Dashboard');
        }

        // For modules panels
        return $this->hasRole(Utils::getSuperAdminName()) || $this->can('view_module');
    }

    public function hasAdminAccess(): bool
    {
        return $this->hasRole(Utils::getSuperAdminName()) || $this->can('page_Dashboard');
    }
}
