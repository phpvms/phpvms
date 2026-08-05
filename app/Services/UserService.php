<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Service;
use App\Enums\PirepState;
use App\Enums\UserState;
use App\Events\UserStateChanged;
use App\Events\UserStatsChanged;
use App\Exceptions\PilotIdNotFound;
use App\Exceptions\PilotIdRangeExhausted;
use App\Exceptions\UserPilotIdExists;
use App\Models\Airline;
use App\Models\Bid;
use App\Models\Pirep;
use App\Models\Rank;
use App\Models\Role;
use App\Models\Typerating;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use App\Support\Units\Time;
use App\Support\Utils;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserService extends Service
{
    /**
     * Find the user and return them with all of the data properly attached
     */
    public function getUser(int $user_id, bool $with_subfleets = true): ?User
    {
        $with = ['airline', 'bids', 'rank'];

        if ($with_subfleets) {
            $with[] = 'rank.subfleets';
        }

        /** @var ?User $user */
        $user = User::with($with)->find($user_id);

        if (empty($user)) {
            return null;
        }

        if ($user->state === UserState::DELETED) {
            return null;
        }

        if ($with_subfleets && $user->rank !== null) {
            $user->rank->setRelation(
                'subfleets',
                $user->allowedSubfleets()->with(['aircraft', 'fares'])->get(),
            );
        }

        return $user;
    }

    /**
     * Get user-defined custom field values for a user, applying the public/private/internal
     * visibility filter. Absorbed verbatim from the deleted UserRepository::getUserFields()
     * (3-valued contract preserved).
     *
     * @param  bool|null                  $only_public_fields   When true:  return only public fields (private = false).
     *                                                          When false: return only private fields (private = true).
     *                                                          When null:  return all visibility-allowed fields (no private filter).
     * @param  bool                       $with_internal_fields When true:  also include internal fields.
     *                                                          When false: exclude internal fields entirely.
     * @return Collection<int, UserField>
     */
    public function getUserFields(
        User $user,
        ?bool $only_public_fields = null,
        bool $with_internal_fields = false,
    ): Collection {
        $fields = UserField::when(!$with_internal_fields, fn ($query) => $query->where('internal', false));

        if (is_bool($only_public_fields)) {
            $fields = $fields->where(['private' => !$only_public_fields]);
        }

        $fields = $fields->get();

        return $fields->map(function (UserField $field, $_) use ($user): UserField {
            foreach ($user->fields as $userFieldValue) {
                if ($userFieldValue->field->slug === $field->slug) {
                    $field->value = $userFieldValue->value;
                }
            }

            return $field;
        });
    }

    /**
     * Count users in PENDING state (awaiting registration approval).
     */
    public function getPendingCount(): int
    {
        return User::pending()->count();
    }

    /**
     * Register a pilot. Also attaches the initial roles
     * required, and then triggers the UserRegistered event
     *
     * @param array $attrs Array with the user data
     * @param array $roles List of "display_name" of groups to assign
     */
    public function createUser(array $attrs, array $roles = [], ?int $state = null): User
    {
        $user = DB::transaction(function () use ($attrs, $roles, $state): User {
            $user = User::create($attrs);
            $user->api_key = Utils::generateApiKey();
            $user->curr_airport_id = $user->home_airport_id;

            // Determine if we want to auto accept
            if ($state === null && setting('pilots.auto_accept') === true) {
                $user->state = UserState::ACTIVE;
            } elseif ($state === null) {
                $user->state = UserState::PENDING;
            }

            $user->save();

            // Attach any additional roles
            foreach ($roles as $role) {
                $this->addUserToRole($user, $role);
            }

            // Let's check their rank and where they should start
            $this->calculatePilotRank($user);
            $user->refresh();

            return $user;
        });

        event(new Registered($user));

        return $user;
    }

    /**
     * Remove the user. But don't actually delete them - set the name to deleted, email to
     * something random
     *
     *
     * @throws Exception
     */
    public function removeUser(User $user): void
    {
        // Detach all roles from this user
        foreach ($user->roles as $role) {
            $user->removeRole($role);
        }

        // Delete any fields which might have personal information
        UserFieldValue::where('user_id', $user->id)->delete();

        // Remove any bids
        Bid::where('user_id', $user->id)->delete();

        // If this user has PIREPs, do a soft delete. Otherwise, just delete them outright
        if ($user->pireps->count() > 0) {
            $user->name = 'Deleted User';
            $user->email = Utils::generateApiKey().'@deleted-user.com';
            $user->api_key = Utils::generateApiKey();
            $user->password = Hash::make(Utils::generateApiKey());
            $user->state = UserState::DELETED;
            $user->save();
        } else {
            $user->forceDelete();
        }
    }

    /**
     * Add a user to a given role
     */
    public function addUserToRole(User $user, string $roleName): User
    {
        $role = Role::where(['name' => $roleName])->first();
        $user->assignRole($role);

        return $user;
    }

    /**
     * Find and return the next available pilot ID, honoring the
     * `pilots.id_range_*`, `pilots.id_fill_gaps`, and `pilots.id_reuse_deleted` settings.
     *
     * @throws PilotIdRangeExhausted when a range is enabled and no ID is available
     */
    public function getNextAvailablePilotId(): int
    {
        $reuseDeleted = (bool) setting('pilots.id_reuse_deleted');
        $rangeEnabled = (bool) setting('pilots.id_range_enabled');

        $floor = $rangeEnabled ? max(1, (int) setting('pilots.id_range_start')) : 1;
        $ceil = $rangeEnabled ? (int) setting('pilots.id_range_end') : null;

        $nextId = (bool) setting('pilots.id_fill_gaps')
            ? $this->findLowestAvailablePilotId($floor, $ceil, $reuseDeleted)
            : $this->findNextPilotIdAfterMax($floor, $ceil, $reuseDeleted);

        if ($ceil !== null && $nextId > $ceil) {
            throw new PilotIdRangeExhausted();
        }

        return $nextId;
    }

    /**
     * The lowest untaken pilot ID in `[$floor, $ceil]`, found with a single
     * self-left-join query rather than looping over every ID in PHP.
     */
    private function findLowestAvailablePilotId(int $floor, ?int $ceil, bool $reuseDeleted): int
    {
        if (!$this->isPilotIdTaken($floor, $reuseDeleted)) {
            return $floor;
        }

        $table = new User()->getTable();

        $query = DB::table($table.' as u1')
            ->leftJoin($table.' as u2', function ($join) use ($reuseDeleted): void {
                $join->on('u2.pilot_id', '=', DB::raw('u1.pilot_id + 1'));

                if ($reuseDeleted) {
                    $join->whereNull('u2.deleted_at');
                }
            })
            ->whereNull('u2.pilot_id')
            ->where('u1.pilot_id', '>=', $floor);

        if ($reuseDeleted) {
            $query->whereNull('u1.deleted_at');
        }

        if ($ceil !== null) {
            $query->where('u1.pilot_id', '<', $ceil);
        }

        $nextId = $query->min(DB::raw('u1.pilot_id + 1'));

        return $nextId !== null ? (int) $nextId : ($ceil ?? $floor) + 1;
    }

    /**
     * max(taken) + 1, clamped to at least $floor.
     *
     * When $ceil is set, the max is taken only over IDs within [$floor, $ceil],
     * so pilots grandfathered in above an enabled range don't make every
     * registration look like the range is exhausted.
     */
    private function findNextPilotIdAfterMax(int $floor, ?int $ceil, bool $reuseDeleted): int
    {
        $query = $this->takenPilotIdsQuery($reuseDeleted);

        if ($ceil !== null) {
            $query->whereBetween('pilot_id', [$floor, $ceil]);
        }

        $max = (int) $query->max('pilot_id');

        return max($max + 1, $floor);
    }

    /**
     * Whether an active (or, when $reuseDeleted is off, soft-deleted) user already holds this ID.
     */
    private function isPilotIdTaken(int $pilotId, bool $reuseDeleted): bool
    {
        return $this->takenPilotIdsQuery($reuseDeleted)->where('pilot_id', $pilotId)->exists();
    }

    /**
     * Base query over users whose pilot_id counts as "taken", per `pilots.id_reuse_deleted`.
     */
    private function takenPilotIdsQuery(bool $reuseDeleted)
    {
        $query = User::query();

        if (!$reuseDeleted) {
            $query->withTrashed();
        }

        return $query;
    }

    /**
     * Find the next available pilot ID and set the current user's pilot_id to it.
     * Called from UserObserver right now after a record is created
     *
     * The migration for this feature dropped the DB unique index on
     * users.pilot_id, so the compute+save is no longer guarded by the
     * database. A cache lock serializes assignment across concurrent
     * requests so two registrations can't compute the same next ID.
     *
     * @throws PilotIdRangeExhausted
     */
    public function findAndSetPilotId(User $user): User
    {
        if ($user->pilot_id !== null && $user->pilot_id > 0) {
            return $user;
        }

        return Cache::lock('pilot-id-assignment', 10)->block(5, fn () => DB::transaction(function () use ($user): User {
            $user->pilot_id = $this->getNextAvailablePilotId();
            $user->save();

            Log::info('Set pilot ID for user '.$user->id.' to '.$user->pilot_id);

            return $user;
        }));
    }

    /**
     * Return true or false if a pilot ID already exists
     */
    public function isPilotIdAlreadyUsed(int $pilot_id): bool
    {
        return User::where('pilot_id', '=', $pilot_id)->exists();
    }

    /**
     * Change a user's pilot ID
     *
     * Guarded by the same cache lock as findAndSetPilotId(), since the DB
     * no longer enforces uniqueness on pilot_id and this also relies on a
     * check-then-write.
     *
     * @throws UserPilotIdExists
     */
    public function changePilotId(User $user, int $pilot_id): User
    {
        if ($user->pilot_id === $pilot_id) {
            return $user;
        }

        return Cache::lock('pilot-id-assignment', 10)->block(5, fn () => DB::transaction(function () use ($user, $pilot_id): User {
            if ($this->isPilotIdTaken($pilot_id, (bool) setting('pilots.id_reuse_deleted'))) {
                Log::error('User with id '.$pilot_id.' already exists');

                throw new UserPilotIdExists($user);
            }

            $old_id = $user->pilot_id;
            $user->pilot_id = $pilot_id;
            $user->save();

            Log::info('Changed pilot ID for user '.$user->id.' from '.$old_id.' to '.$user->pilot_id);

            return $user;
        }));
    }

    /**
     * Split a given pilot ID into an airline and ID portions
     */
    public function findUserByPilotId(string $pilot_id): User
    {
        $pilot_id = trim($pilot_id);
        if ($pilot_id === '' || $pilot_id === '0') {
            throw new PilotIdNotFound('');
        }

        $airlines = Airline::all(['id', 'icao', 'iata']);

        $ident_str = null;
        $pilot_id = strtoupper($pilot_id);

        /** @var Airline $airline */
        foreach ($airlines as $airline) {
            if (str_starts_with($pilot_id, $airline->icao)) {
                $ident_str = $airline->icao;
                break;
            }

            if (!empty($airline->iata) && str_starts_with($pilot_id, (string) $airline->iata)) {
                $ident_str = $airline->iata;
                break;
            }
        }

        if (empty($ident_str) || empty($airline)) {
            throw new PilotIdNotFound($pilot_id);
        }

        $parsed_pilot_id = str_replace($ident_str, '', $pilot_id);
        if ($parsed_pilot_id === '' || !ctype_digit($parsed_pilot_id)) {
            throw new PilotIdNotFound($pilot_id);
        }

        $user = User::where(['airline_id' => $airline->id, 'pilot_id' => $parsed_pilot_id])->first();
        if (empty($user)) {
            throw new PilotIdNotFound($pilot_id);
        }

        return $user;
    }

    /**
     * Return all of the users that are determined to be on leave. Only goes through the
     * currently active users. If the user doesn't have a PIREP, then the creation date
     * of the user record is used to determine the difference
     */
    public function findUsersOnLeave()
    {
        $leave_days = setting('pilots.auto_leave_days');
        if ($leave_days === 0) {
            return [];
        }

        $date = Carbon::now('UTC');
        $users = User::where('state', UserState::ACTIVE)->get();

        return $users->filter(function (User $user, $i) use ($date, $leave_days): bool {
            // If any role for this user has the "disable_activity_check" feature activated, skip this user
            foreach ($user->roles()->get() as $role) {
                /** @var Role $role */
                if ($role->disable_activity_checks) {
                    return false;
                }
            }

            // If they haven't submitted a PIREP, use the date that the user was created
            $last_pirep = Pirep::where(['user_id' => $user->id])->latest('submitted_at')->first();
            $diff_date = $last_pirep ? $last_pirep->created_at : $user->created_at;

            // See if the difference is larger than what the setting calls for
            return abs($date->diffInDays($diff_date)) > $leave_days;
        });
    }

    /**
     * Change the user's state. PENDING to ACCEPTED, etc
     * Send out an email
     */
    public function changeUserState(User $user, $old_state): User
    {
        if ($user->state === $old_state) {
            return $user;
        }

        Log::info('User '.$user->ident.' state changing from '.$old_state->getLabel().' to '.$user->state->getLabel());

        event(new UserStateChanged($user, $old_state));

        return $user;
    }

    /**
     * Adjust the number of flights a user has. Triggers
     * UserStatsChanged event
     */
    public function adjustFlightCount(User $user, int $count): User
    {
        $user->refresh();
        $old_value = $user->flights;
        $user->flights += $count;
        $user->save();

        event(new UserStatsChanged($user, 'flights', $old_value));

        return $user;
    }

    /**
     * Update a user's flight times
     */
    public function adjustFlightTime(User $user, int $minutes): User
    {
        $user->refresh();
        $user->flight_time += $minutes;
        $user->save();

        return $user;
    }

    /**
     * See if a pilot's rank has change. Triggers the UserStatsChanged event
     */
    public function calculatePilotRank(User $user): User
    {
        $user->refresh();

        // If their current rank is one they were assigned, then
        // don't change away from it automatically.
        if ($user->rank && $user->rank->auto_promote === false) {
            return $user;
        }

        // If we should count their transfer hours?
        if (setting('pilots.count_transfer_hours', false) === true) {
            $pilot_hours = new Time($user->flight_time + $user->transfer_time);
        } else {
            $pilot_hours = new Time($user->flight_time);
        }

        // The current rank's hours are over the pilot's current hours,
        // so assume that they were "placed" here by an admin so don't
        // bother with updating it
        if ($user->rank && $user->rank->hours > $pilot_hours->hours) {
            return $user;
        }

        $old_rank = $user->rank;
        $original_rank_id = $user->rank_id;

        $ranks = Rank::where('auto_promote', true)
            ->orderBy('hours', 'asc')->get();

        foreach ($ranks as $rank) {
            if ($rank->hours > $pilot_hours->hours) {
                break;
            }

            $user->rank_id = $rank->id;
        }

        // Only trigger the event/update if there's been a change
        if ($user->rank_id !== $original_rank_id) {
            $user->save();
            $user->refresh();
            event(new UserStatsChanged($user, 'rank', $old_rank));
        }

        return $user;
    }

    /**
     * Set the user's status to being on leave
     */
    public function setStatusOnLeave(User $user): User
    {
        $user->refresh();
        $user->state = UserState::ON_LEAVE;
        $user->save();

        event(new UserStateChanged($user, UserState::ON_LEAVE));

        $user->refresh();

        return $user;
    }

    /**
     * Recalculate the stats for all active users
     */
    public function recalculateAllUserStats(): void
    {
        User::notRejected()
            ->get(['id', 'name', 'airline_id'])
            ->each(function (User $user): void {
                $this->recalculateStats($user);
            });
    }

    /**
     * Recount/update all of the stats for a user
     */
    public function recalculateStats(User $user): User
    {
        // Recalc their hours
        $w = [
            'user_id' => $user->id,
            'state'   => PirepState::ACCEPTED,
        ];

        $pirep_count = Pirep::where($w)->count();
        $user->flights = $pirep_count;

        $flight_time = Pirep::where($w)->sum('flight_time');
        $user->flight_time = $flight_time;

        $user->save();

        // Recalc the rank
        $this->calculatePilotRank($user);

        Log::info('User '.$user->ident.' updated; pirep count='.$pirep_count.', rank='.$user->rank->name.', flight_time='.$user->flight_time.' minutes');

        $user->save();

        return $user;
    }

    /**
     * Attach a type rating to the user
     */
    public function addUserToTypeRating(User $user, Typerating $typerating): User
    {
        $user->typeratings()->syncWithoutDetaching([$typerating->id]);
        $user->save();
        $user->refresh();

        return $user;
    }

    /**
     * Detach a type rating from the user
     */
    public function removeUserFromTypeRating(User $user, Typerating $typerating): User
    {
        $user->typeratings()->detach($typerating->id);
        $user->save();
        $user->refresh();

        return $user;
    }

    public function retrieveDiscordPrivateChannelId(User $user): void
    {
        if (is_null(config('services.discord.bot_token'))) {
            return;
        }

        try {
            $httpClient = new Client();

            $response = $httpClient->post('https://discord.com/api/users/@me/channels', [
                'headers' => [
                    'Authorization' => 'Bot '.config('services.discord.bot_token'),
                ],
                'json' => [
                    'recipient_id' => $user->discord_id,
                ],
            ]);

            $privateChannel = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR)['id'];
            $user->update([
                'discord_private_channel_id' => $privateChannel,
            ]);
        } catch (Exception|GuzzleException $e) {
            Log::error('Discord OAuth Error: '.$e->getMessage());
        }
    }
}
