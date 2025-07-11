<?php

namespace App\Services\LegacyImporter;

use App\Models\Enums\UserState;
use App\Models\User;
use App\Services\UserService;
use App\Support\Units\Time;
use App\Support\Utils;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserImport extends BaseImporter
{
    protected $table = 'pilots';

    protected $idField = 'pilotid';

    private UserService $userSvc;

    public function run($start = 0): void
    {
        $this->comment('--- USER IMPORT ---');

        $this->userSvc = app(UserService::class);

        $count = 0;
        $first_row = true;
        $rows = $this->db->readRows($this->table, $this->idField, $start);
        foreach ($rows as $row) {
            $pilot_id = $row->pilotid; // This isn't their actual ID
            $name = $row->firstname.' '.$row->lastname;

            // Figure out which airline, etc, they belong to
            $airline_id = $this->idMapper->getMapping('airlines', $row->code);
            // Log::info('User airline from '.$row->code.' to ID '.$airline_id);

            $rank_id = $this->idMapper->getMapping('ranks', $row->rank);
            $state = $this->getUserState($row->retired);

            if ($first_row) {
                $new_password = 'admin';
                $first_row = false;
            } else {
                $new_password = Str::random(60);
            }

            // Look for a user with that pilot ID already. If someone has it
            if ($this->userSvc->isPilotIdAlreadyUsed($pilot_id)) {
                Log::info('User with pilot id '.$pilot_id.' exists');

                // Is this the same user? If not, get a new pilot ID
                $user_exist = User::where('pilot_id', $pilot_id)->first();
                if ($user_exist->email !== $row->email) {
                    $pilot_id = $this->userSvc->getNextAvailablePilotId();
                }
            }

            $attrs = [
                'pilot_id'          => $pilot_id,
                'callsign'          => $pilot_id,
                'name'              => $name,
                'password'          => Hash::make($new_password),
                'api_key'           => Utils::generateApiKey(),
                'airline_id'        => $airline_id,
                'rank_id'           => $rank_id,
                'home_airport_id'   => $row->hub,
                'curr_airport_id'   => $row->hub,
                'country'           => $row->location,
                'flights'           => (int) $row->totalflights,
                'flight_time'       => Time::hoursToMinutes($row->totalhours),
                'transfer_time'     => filled($row->transferhours) ? Time::hoursToMinutes($row->transferhours) : null,
                'state'             => $state,
                'created_at'        => $this->parseDate($row->joindate),
                'email_verified_at' => now(),
            ];

            $user = User::updateOrCreate(['email' => $row->email], $attrs);
            $this->idMapper->addMapping('users', $row->pilotid, $user->id);

            $this->updateUserRoles($user, $row->pilotid);

            if ($user->wasRecentlyCreated) {
                $count++;
            }
        }

        $this->info('Imported '.$count.' users');
    }

    /**
     * Update a user's roles and add them to the proper ones
     *
     * @param string $old_pilot_id
     */
    protected function updateUserRoles(User $user, $old_pilot_id)
    {
        // Figure out what other groups they belong to... read from the old table, and map
        // them to the new group(s)
        $old_user_groups = $this->db->findBy('groupmembers', ['pilotid' => $old_pilot_id]);
        foreach ($old_user_groups as $oldGroup) {
            $newRoleId = $this->idMapper->getMapping('group', $oldGroup->groupid);

            // This role should be ignored
            if ($newRoleId === -1) {
                continue;
            }

            if ($newRoleId !== false) {
                $user->assignRole($newRoleId);
            }
        }
    }

    /**
     * Get the user's new state from their original state
     */
    protected function getUserState($state): int
    {
        // Return active for now, let the stats/cron determine the status later
        return UserState::ACTIVE;
        /*$state = (int) $state;

        // Declare array of classic states
        $phpvms_classic_states = [
            'ACTIVE'   => 0,
            'INACTIVE' => 1,
            'BANNED'   => 2,
            'ON_LEAVE' => 3,
        ];

        // Decide which state they will be in accordance with v7
        if ($state === $phpvms_classic_states['ACTIVE']) {
            return UserState::ACTIVE;
        }

        if ($state === $phpvms_classic_states['INACTIVE']) {
            // TODO: Make an inactive state?
            return UserState::REJECTED;
        }

        if ($state === $phpvms_classic_states['BANNED']) {
            return UserState::SUSPENDED;
        }

        if ($state === $phpvms_classic_states['ON_LEAVE']) {
            return UserState::ON_LEAVE;
        }

        $this->error('Unknown status: '.$state);

        return UserState::ACTIVE;*/
    }
}
