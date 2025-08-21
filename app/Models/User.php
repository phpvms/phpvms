<?php

namespace App\Models;

use App\Models\Enums\JournalType;
use App\Models\Traits\JournalTrait;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Kyslik\ColumnSortable\Sortable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

/**
 * @property int                             $id
 * @property int|null                        $pilot_id
 * @property string|null                     $callsign
 * @property string|null                     $name
 * @property string                          $email
 * @property string                          $password
 * @property string|null                     $api_key
 * @property int                             $airline_id
 * @property int|null                        $rank_id
 * @property string                          $discord_id
 * @property string                          $discord_private_channel_id
 * @property string                          $vatsim_id
 * @property string                          $ivao_id
 * @property string|null                     $country
 * @property string|null                     $home_airport_id
 * @property string|null                     $curr_airport_id
 * @property string|null                     $last_pirep_id
 * @property int                             $flights
 * @property int|null                        $flight_time
 * @property int|null                        $transfer_time
 * @property File|null                       $avatar
 * @property string|null                     $timezone
 * @property int|null                        $status
 * @property int|null                        $state
 * @property bool|null                       $toc_accepted
 * @property bool|null                       $opt_in
 * @property int|null                        $active
 * @property string|null                     $last_ip
 * @property \Illuminate\Support\Carbon|null $lastlogin_at
 * @property string|null                     $remember_token
 * @property string|null                     $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\Airline|null $airline
 * @property-read mixed $atc
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Award> $awards
 * @property-read int|null $awards_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Bid> $bids
 * @property-read int|null $bids_count
 * @property-read \App\Models\Airport|null $current_airport
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserFieldValue> $fields
 * @property-read int|null $fields_count
 * @property-read \App\Models\Airport|null $home_airport
 * @property-read mixed $ident
 * @property-read \App\Models\Journal|null $journal
 * @property-read \App\Models\Pirep|null $last_pirep
 * @property-read \App\Models\Airport|null $location
 * @property-read mixed $name_private
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserOAuthToken> $oauth_tokens
 * @property-read int|null $oauth_tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Pirep> $pireps
 * @property-read int|null $pireps_count
 * @property-read \App\Models\Rank|null $rank
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Typerating> $typeratings
 * @property-read int|null $typeratings_count
 * @property mixed $tz
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static Builder<static>|User            newModelQuery()
 * @method static Builder<static>|User            newQuery()
 * @method static Builder<static>|User            onlyTrashed()
 * @method static Builder<static>|User            permission($permissions, $without = false)
 * @method static Builder<static>|User            query()
 * @method static Builder<static>|User            role($roles, $guard = null, $without = false)
 * @method static Builder<static>|User            sortable($defaultParameters = null)
 * @method static Builder<static>|User            whereActive($value)
 * @method static Builder<static>|User            whereAirlineId($value)
 * @method static Builder<static>|User            whereApiKey($value)
 * @method static Builder<static>|User            whereAvatarUrl($value)
 * @method static Builder<static>|User            whereCallsign($value)
 * @method static Builder<static>|User            whereCountry($value)
 * @method static Builder<static>|User            whereCreatedAt($value)
 * @method static Builder<static>|User            whereCurrAirportId($value)
 * @method static Builder<static>|User            whereDeletedAt($value)
 * @method static Builder<static>|User            whereDiscordId($value)
 * @method static Builder<static>|User            whereDiscordPrivateChannelId($value)
 * @method static Builder<static>|User            whereEmail($value)
 * @method static Builder<static>|User            whereEmailVerifiedAt($value)
 * @method static Builder<static>|User            whereFlightTime($value)
 * @method static Builder<static>|User            whereFlights($value)
 * @method static Builder<static>|User            whereHomeAirportId($value)
 * @method static Builder<static>|User            whereId($value)
 * @method static Builder<static>|User            whereIvaoId($value)
 * @method static Builder<static>|User            whereLastIp($value)
 * @method static Builder<static>|User            whereLastPirepId($value)
 * @method static Builder<static>|User            whereLastloginAt($value)
 * @method static Builder<static>|User            whereName($value)
 * @method static Builder<static>|User            whereNotes($value)
 * @method static Builder<static>|User            whereOptIn($value)
 * @method static Builder<static>|User            wherePassword($value)
 * @method static Builder<static>|User            wherePilotId($value)
 * @method static Builder<static>|User            whereRankId($value)
 * @method static Builder<static>|User            whereRememberToken($value)
 * @method static Builder<static>|User            whereState($value)
 * @method static Builder<static>|User            whereStatus($value)
 * @method static Builder<static>|User            whereTimezone($value)
 * @method static Builder<static>|User            whereTocAccepted($value)
 * @method static Builder<static>|User            whereTransferTime($value)
 * @method static Builder<static>|User            whereUpdatedAt($value)
 * @method static Builder<static>|User            whereVatsimId($value)
 * @method static Builder<static>|User            withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|User            withoutPermission($permissions)
 * @method static Builder<static>|User            withoutRole($roles, $guard = null)
 * @method static Builder<static>|User            withoutTrashed()
 *
 * @mixin \Eloquent
 */
class User extends Authenticatable implements FilamentUser, HasAvatar, MustVerifyEmail
{
    use HasFactory;
    use HasRelationships;
    use HasRoles;
    use JournalTrait;
    use LogsActivity;
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
        'vatsim_id',
        'ivao_id',
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
        'discord_private_channel_id',
        'password',
        'last_ip',
        'remember_token',
        'notes',
    ];

    public static array $rules = [
        'name'     => 'required',
        'email'    => 'required|email',
        'pilot_id' => 'required|integer',
        'callsign' => 'nullable|max:4',
    ];

    public $sortable = [
        'id',
        'name',
        'email',
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
        'state',
        'vatsim_id',
        'ivao_id',
    ];

    /**
     * Format the pilot ID/ident
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
            get: function (mixed $value) {
                if (!$value) {
                    return null;
                }

                return new File(['path' => $value]);
            }
        );
    }

    /**
     * @param  mixed  $size Size of the gravatar, in pixels
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
        /** @var ?File $avatar */
        $avatar = $this->avatar;
        if (empty($avatar)) {
            return $this->gravatar();
        }

        return $avatar->url;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logExcept(array_merge($this->hidden, ['created_at', 'updated_at']))
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
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
        return $this->belongsToMany(Award::class, 'user_awards')->withTimestamps()->withTrashed();
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

    public function oauth_tokens(): HasMany
    {
        return $this->hasMany(UserOAuthToken::class, 'user_id');
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
        // For phpvms panels
        if ($panel->getId() === 'admin' || $panel->getId() === 'system') {
            return $this->hasAdminAccess();
        }

        // For modules panels
        return $this->hasRole(Utils::getSuperAdminName()) || $this->can('view_module');
    }

    public function hasAdminAccess(): bool
    {
        return $this->hasRole(Utils::getSuperAdminName().'|admin') || $this->can('page_Dashboard');
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar?->url;
    }

    protected function casts(): array
    {
        return [
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
    }
}
