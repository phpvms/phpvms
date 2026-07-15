<?php

namespace App\Models;

use App\Enums\JournalType;
use App\Enums\UserState;
use App\Observers\UserObserver;
use App\Services\PermissionRegistry;
use App\Traits\JournalTrait;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Kyslik\ColumnSortable\Sortable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Override;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

/**
 * @property int            $id
 * @property int|null       $pilot_id
 * @property string|null    $callsign
 * @property string|null    $name
 * @property string         $email
 * @property string         $password
 * @property string|null    $api_key
 * @property int            $airline_id
 * @property int|null       $rank_id
 * @property string         $discord_id
 * @property string         $discord_private_channel_id
 * @property string         $vatsim_id
 * @property string         $ivao_id
 * @property string|null    $country
 * @property string|null    $home_airport_id
 * @property string|null    $curr_airport_id
 * @property string|null    $last_pirep_id
 * @property int            $flights
 * @property int|null       $flight_time
 * @property int|null       $transfer_time
 * @property File|null      $avatar
 * @property string|null    $timezone
 * @property int|null       $status
 * @property UserState|null $state
 * @property bool|null      $toc_accepted
 * @property bool|null      $opt_in
 * @property int|null       $active
 * @property string|null    $last_ip
 * @property Carbon|null    $lastlogin_at
 * @property string|null    $remember_token
 * @property string|null    $notes
 * @property Carbon|null    $created_at
 * @property Carbon|null    $updated_at
 * @property Carbon|null    $deleted_at
 * @property Carbon|null    $email_verified_at
 * @property string|null    $simbrief_username
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Airline|null $airline
 * @property-read string $atc
 * @property-read Collection<int, Award> $awards
 * @property-read int|null $awards_count
 * @property-read Collection<int, Bid> $bids
 * @property-read int|null $bids_count
 * @property-read Airport|null $current_airport
 * @property-read Collection<int, UserFieldValue> $fields
 * @property-read int|null $fields_count
 * @property-read Airport|null $home_airport
 * @property-read string $ident
 * @property-read Journal|null $journal
 * @property-read Pirep|null $last_pirep
 * @property-read Airport|null $location
 * @property-read string $name_private
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, UserOAuthToken> $oauth_tokens
 * @property-read int|null $oauth_tokens_count
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, Pirep> $pireps
 * @property-read int|null $pireps_count
 * @property-read Rank|null $rank
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read Collection<int, Permission> $teams
 * @property-read int|null $teams_count
 * @property-read Collection<int, Typerating> $typeratings
 * @property-read int|null $typeratings_count
 * @property mixed $tz
 *
 * @method static Builder<static>|User active()
 * @method static UserFactory          factory($count = null, $state = [])
 * @method static Builder<static>|User forAirline(int $airlineId)
 * @method static Builder<static>|User inState(int $state)
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User notRejected()
 * @method static Builder<static>|User onlyTrashed()
 * @method static Builder<static>|User pending()
 * @method static Builder<static>|User permission($permissions, bool $without = false)
 * @method static Builder<static>|User query()
 * @method static Builder<static>|User role($roles, ?string $guard = null, bool $without = false)
 * @method static Builder<static>|User sortable($defaultParameters = null)
 * @method static Builder<static>|User team($teams, bool $without = false)
 * @method static Builder<static>|User whereActive($value)
 * @method static Builder<static>|User whereAirlineId($value)
 * @method static Builder<static>|User whereApiKey($value)
 * @method static Builder<static>|User whereAvatar($value)
 * @method static Builder<static>|User whereCallsign($value)
 * @method static Builder<static>|User whereCountry($value)
 * @method static Builder<static>|User whereCreatedAt($value)
 * @method static Builder<static>|User whereCurrAirportId($value)
 * @method static Builder<static>|User whereDeletedAt($value)
 * @method static Builder<static>|User whereDiscordId($value)
 * @method static Builder<static>|User whereDiscordPrivateChannelId($value)
 * @method static Builder<static>|User whereEmail($value)
 * @method static Builder<static>|User whereEmailVerifiedAt($value)
 * @method static Builder<static>|User whereFlightTime($value)
 * @method static Builder<static>|User whereFlights($value)
 * @method static Builder<static>|User whereHomeAirportId($value)
 * @method static Builder<static>|User whereId($value)
 * @method static Builder<static>|User whereIvaoId($value)
 * @method static Builder<static>|User whereLastIp($value)
 * @method static Builder<static>|User whereLastPirepId($value)
 * @method static Builder<static>|User whereLastloginAt($value)
 * @method static Builder<static>|User whereName($value)
 * @method static Builder<static>|User whereNotes($value)
 * @method static Builder<static>|User whereOptIn($value)
 * @method static Builder<static>|User wherePassword($value)
 * @method static Builder<static>|User wherePilotId($value)
 * @method static Builder<static>|User whereRankId($value)
 * @method static Builder<static>|User whereRememberToken($value)
 * @method static Builder<static>|User whereSimbriefUsername($value)
 * @method static Builder<static>|User whereState($value)
 * @method static Builder<static>|User whereStatus($value)
 * @method static Builder<static>|User whereTimezone($value)
 * @method static Builder<static>|User whereTocAccepted($value)
 * @method static Builder<static>|User whereTransferTime($value)
 * @method static Builder<static>|User whereUpdatedAt($value)
 * @method static Builder<static>|User whereVatsimId($value)
 * @method static Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|User withoutPermission($permissions)
 * @method static Builder<static>|User withoutRole($roles, ?string $guard = null)
 * @method static Builder<static>|User withoutTeam($teams)
 * @method static Builder<static>|User withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[ObservedBy(UserObserver::class)]
class User extends Authenticatable implements FilamentUser, HasAvatar, MustVerifyEmail, OAuthenticatable
{
    use HasApiTokens;
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
        'simbrief_username',
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
            get: function ($_, array $attrs): string {
                $length = setting('pilots.id_length');
                $ident_code = filled(setting('pilots.id_code')) ? setting(
                    'pilots.id_code'
                ) : optional($this->airline)->icao;

                return $ident_code.str_pad((string) $attrs['pilot_id'], $length, '0', STR_PAD_LEFT);
            }
        );
    }

    /**
     * Format the pilot atc callsign, either return alphanumeric callsign or ident
     */
    public function atc(): Attribute
    {
        return Attribute::make(
            get: function ($_, array $attrs): string {
                $ident_code = filled(setting('pilots.id_code')) ? setting('pilots.id_code') : optional($this->airline)->icao;

                return filled($attrs['callsign']) ? $ident_code.$attrs['callsign'] : $ident_code.$attrs['pilot_id'];
            }
        );
    }

    /**
     * Normalize email to lowercase so lookups and the unique constraint behave
     * consistently across case-sensitive (PostgreSQL) and case-insensitive (MySQL) drivers.
     */
    public function email(): Attribute
    {
        return Attribute::make(
            set: static fn (?string $value): ?string => $value === null ? null : mb_strtolower(trim($value)),
        );
    }

    /**
     * Return a "privatized" version of someones name - First and middle names full, last name initials
     */
    public function namePrivate(): Attribute
    {
        return Attribute::make(
            get: function ($_, array $attrs): string {
                $name_parts = explode(' ', (string) $attrs['name']);
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
            set: fn ($value): array => [
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
            get: function (mixed $value): ?File {
                if (!$value) {
                    return null;
                }

                return new File(['path' => $value]);
            }
        );
    }

    /**
     * Route Discord notifications to this user's DM channel, which
     * UserService::retrieveDiscordPrivateChannelId() opens and stores when they
     * link their Discord account, and OAuthController clears when they unlink.
     *
     * Null for a user who never linked Discord (the common case), which the
     * notifier treats as "nowhere to send" and skips, leaving the other
     * channels in a notification's via() list unaffected.
     */
    public function routeNotificationForDiscord(): ?string
    {
        return $this->discord_private_channel_id ?: null;
    }

    /**
     * @param mixed $size Size of the gravatar, in pixels
     */
    public function gravatar($size = null): string
    {
        $default = config('phpvms.avatar.default');

        $uri = config('phpvms.avatar.gravatar_url').md5(strtolower(trim($this->email))).'?d='.urlencode((string) $default);

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

    /**
     * Composable query for the subfleets this user is allowed to operate, given
     * the current restrict_aircraft_to_rank / restrict_aircraft_to_typerating
     * settings. Callers chain ->get(), ->paginate($per), ->pluck('id'), etc.
     */
    public function allowedSubfleets(): Builder
    {
        return Subfleet::query()->allowedFor($this);
    }

    /**
     * Composable query for the aircraft this user is allowed to operate.
     * Pass a Flight to apply the only_aircraft_at_dpt_airport setting against
     * that flight's departure airport.
     */
    public function allowedAircraft(?Flight $flight = null): Builder
    {
        return Aircraft::query()->allowedFor($this, $flight);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // For phpvms panels
        if ($panel->getId() === 'admin' || $panel->getId() === 'system') {
            return $this->hasAdminAccess();
        }

        // For module panels: the panel id equals the module key, so access is
        // gated by the per-module `access:{module-key}` permission (registered
        // via PermissionRegistry), with the legacy `view:modules` as fallback.
        if ($this->hasRole(Role::superAdminName())) {
            return true;
        }

        if ($this->can('access:'.$panel->getId())) {
            return true;
        }

        // Each module panel is gated by its own `access:<module>` permission,
        // with the generic `view:modules` as a fallback for any module panel.
        $moduleKey = app(PermissionRegistry::class)->moduleKeyForPanel($panel);

        if ($moduleKey !== null && $this->can('access:'.$moduleKey)) {
            return true;
        }

        return $this->can('view:modules');
    }

    public function hasAdminAccess(): bool
    {
        if ($this->hasRole(Role::superAdminName().'|admin')) {
            return true;
        }

        return $this->can('view:dashboard');
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar?->url;
    }

    /**
     * Filter to currently-active users (state = ACTIVE).
     */
    #[Scope]
    protected function active(Builder $q): Builder
    {
        return $q->where('state', UserState::ACTIVE);
    }

    /**
     * Filter to pending users (awaiting registration approval).
     */
    #[Scope]
    protected function pending(Builder $q): Builder
    {
        return $q->where('state', UserState::PENDING);
    }

    /**
     * Filter to users in any specified state.
     */
    #[Scope]
    protected function inState(Builder $q, int $state): Builder
    {
        return $q->where('state', $state);
    }

    /**
     * Filter to users belonging to a specific airline.
     */
    #[Scope]
    protected function forAirline(Builder $q, int $airlineId): Builder
    {
        return $q->where('airline_id', $airlineId);
    }

    /**
     * Filter to users that are not in REJECTED state.
     * Used by recalculateAllUserStats() — broader than active().
     */
    #[Scope]
    protected function notRejected(Builder $q): Builder
    {
        return $q->where('state', '!=', UserState::REJECTED);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'id'                => 'integer',
            'pilot_id'          => 'integer',
            'flights'           => 'integer',
            'flight_time'       => 'integer',
            'transfer_time'     => 'integer',
            'balance'           => 'double',
            'state'             => UserState::class,
            'status'            => 'integer',
            'toc_accepted'      => 'boolean',
            'opt_in'            => 'boolean',
            'lastlogin_at'      => 'datetime',
            'deleted_at'        => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }
}
