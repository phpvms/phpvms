<?php

namespace App\Services;

use App\Contracts\Service;
use App\Events\UserStateChanged;
use App\Events\UserStatsChanged;
use App\Exceptions\PilotIdNotFound;
use App\Exceptions\UserPilotIdExists;
use App\Models\Airline;
use App\Models\Bid;
use App\Models\Enums\PirepState;
use App\Models\Enums\UserState;
use App\Models\Pirep;
use App\Models\Rank;
use App\Models\Role;
use App\Models\Typerating;
use App\Models\User;
use App\Models\UserFieldValue;
use App\Repositories\AircraftRepository;
use App\Repositories\AirlineRepository;
use App\Repositories\SubfleetRepository;
use App\Repositories\UserRepository;
use App\Support\Units\Time;
use App\Support\Utils;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

use function is_array;

class UserService extends Service
{
    public function __construct(
        private readonly AircraftRepository $aircraftRepo,
        private readonly AirlineRepository $airlineRepo,
        private readonly FareService $fareSvc,
        private readonly SubfleetRepository $subfleetRepo,
        private readonly UserRepository $userRepo
    ) {}

    /**
     * Find the user and return them with all of the data properly attached
     */
    public function getUser(int $user_id, bool $with_subfleets = true): ?User
    {
        $with = ['airline', 'bids', 'rank'];

        if ($with_subfleets) {
            $with[] = 'rank.subfleets';
        }

        /** @var User $user */
        $user = $this->userRepo
            ->with($with)
            ->find($user_id);

        if (empty($user)) {
            return null;
        }

        if ($user->state === UserState::DELETED) {
            return null;
        }

        if ($with_subfleets) {
            // Load the proper subfleets to the rank
            $user->rank->subfleets = $this->getAllowableSubfleets($user);
            $user->subfleets = $user->rank->subfleets;
        }

        return $user;
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
        if ($roles !== [] && is_array($roles)) {
            foreach ($roles as $role) {
                $this->addUserToRole($user, $role);
            }
        }

        // Let's check their rank and where they should start
        $this->calculatePilotRank($user);
        $user->refresh();

        event(new Registered($user));

        return $user;
    }

    /**
     * Remove the user. But don't actually delete them - set the name to deleted, email to
     * something random
     *
     *
     * @throws \Exception
     */
    public function removeUser(User $user)
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
     * Find and return the next available pilot ID (usually just the max+1)
     */
    public function getNextAvailablePilotId(): int
    {
        return (int) User::withTrashed()->max('pilot_id') + 1;
    }

    /**
     * Find the next available pilot ID and set the current user's pilot_id to that +1
     * Called from UserObserver right now after a record is created
     */
    public function findAndSetPilotId(User $user): User
    {
        if ($user->pilot_id !== null && $user->pilot_id > 0) {
            return $user;
        }

        $user->pilot_id = $this->getNextAvailablePilotId();
        $user->save();

        Log::info('Set pilot ID for user '.$user->id.' to '.$user->pilot_id);

        return $user;
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
     *
     * @throws UserPilotIdExists
     */
    public function changePilotId(User $user, int $pilot_id): User
    {
        if ($user->pilot_id === $pilot_id) {
            return $user;
        }

        if ($this->isPilotIdAlreadyUsed($pilot_id)) {
            Log::error('User with id '.$pilot_id.' already exists');

            throw new UserPilotIdExists($user);
        }

        $old_id = $user->pilot_id;
        $user->pilot_id = $pilot_id;
        $user->save();

        Log::info('Changed pilot ID for user '.$user->id.' from '.$old_id.' to '.$user->pilot_id);

        return $user;
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

        $airlines = $this->airlineRepo->all(['id', 'icao', 'iata']);

        $ident_str = null;
        $pilot_id = strtoupper($pilot_id);

        /** @var Airline $airline */
        foreach ($airlines as $airline) {
            if (strpos($pilot_id, $airline->icao) !== false) {
                $ident_str = $airline->icao;
                break;
            }

            if (!empty($airline->iata) && strpos($pilot_id, $airline->iata) !== false) {
                $ident_str = $airline->iata;
                break;
            }
        }

        if (empty($ident_str)) {
            throw new PilotIdNotFound($pilot_id);
        }

        $parsed_pilot_id = str_replace($ident_str, '', $pilot_id);
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

        /** @var User $user */
        return $users->filter(function ($user, $i) use ($date, $leave_days) {
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
     * Return the subfleets this user is allowed access to,
     * based on their current Rank and/or by Type Rating
     *
     *
     * @return Collection
     */
    public function getAllowableSubfleets($user, bool $paginate = false)
    {
        $restrict_rank = setting('pireps.restrict_aircraft_to_rank', true);
        $restrict_type = setting('pireps.restrict_aircraft_to_typerating', false);
        $restricted_to = [];

        if ($user) {
            $rank_sf_array = $restrict_rank ? $user->rank->subfleets()->pluck('id')->toArray() : [];
            $type_sf_array = $restrict_type ? $user->rated_subfleets->pluck('id')->toArray() : [];

            if ($restrict_rank && !$restrict_type) {
                $restricted_to = $rank_sf_array;
            } elseif (!$restrict_rank && $restrict_type) {
                $restricted_to = $type_sf_array;
            } elseif ($restrict_rank && $restrict_type) {
                $restricted_to = array_intersect($rank_sf_array, $type_sf_array);
            }
        } else {
            $restrict_rank = false;
            $restrict_type = false;
        }

        $subfleetsQuery = $this->subfleetRepo->when($restrict_rank || $restrict_type, function ($query) use ($restricted_to) {
            return $query->whereIn('id', $restricted_to);
        })->with(['aircraft', 'aircraft.bid', 'fares']);

        $subfleets = $paginate ? $subfleetsQuery->paginate() : $subfleetsQuery->get();

        // Map the subfleets with the proper fare information
        return $subfleets->transform(function ($sf, $key) {
            $sf->fares = $this->fareSvc->getForSubfleet($sf);

            return $sf;
        });
    }

    /**
     * Return a bool if a user is allowed to fly the current aircraft
     *
     *
     * @return bool
     */
    public function aircraftAllowed($user, $aircraft_id)
    {
        $aircraft = $this->aircraftRepo->find($aircraft_id, ['subfleet_id']);
        $subfleets = $this->getAllowableSubfleets($user);
        $subfleet_ids = $subfleets->pluck('id')->toArray();

        return \in_array($aircraft->subfleet_id, $subfleet_ids, true);
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

        Log::info('User '.$user->ident.' state changing from '.UserState::label($old_state).' to '.UserState::label($user->state));

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
        $w = [
            ['state', '!=', UserState::REJECTED],
        ];

        $this->userRepo
            ->findWhere($w, ['id', 'name', 'airline_id'])
            ->each(function ($user, $_) {
                return $this->recalculateStats($user);
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
    public function addUserToTypeRating(User $user, Typerating $typerating)
    {
        $user->typeratings()->syncWithoutDetaching([$typerating->id]);
        $user->save();
        $user->refresh();

        return $user;
    }

    /**
     * Detach a type rating from the user
     */
    public function removeUserFromTypeRating(User $user, Typerating $typerating)
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
        } catch (\Exception $e) {
            Log::error('Discord OAuth Error: '.$e->getMessage());
        } catch (GuzzleException $e) {
            Log::error('Discord OAuth Error: '.$e->getMessage());
        }
    }
}
