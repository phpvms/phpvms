<?php

namespace App\Services\LegacyImporter;

use App\Models\User;
use App\Services\AircraftService;
use App\Services\UserService;

class FinalizeImporter extends BaseImporter
{
    /**
     * Returns a default manifest just so this step gets run
     */
    public function getManifest(): array
    {
        return [
            [
                'importer' => static::class,
                'start'    => 0,
                'end'      => 1,
                'message'  => 'Finalizing import',
            ],
        ];
    }

    /**
     * The start method. Takes the offset to start from
     *
     * @param int $start
     */
    public function run($start = 0): void
    {
        $this->findLastPireps();
        $this->recalculateUserStats();
        $this->clearValueStore();
    }

    /**
     * Go through and set the last PIREP ID for the users
     */
    protected function findLastPireps()
    {
        // TODO
    }

    /**
     * Recalculate all of the user stats
     */
    protected function recalculateUserStats()
    {
        $this->comment('--- RECALCULATING USER STATS ---');

        /** @var UserService $userSvc */
        $userSvc = app(UserService::class);

        User::all()->each(function ($user) use ($userSvc): void {
            $userSvc->recalculateStats($user);
        });
    }

    /**
     * Update the aircraft stats with the newest/latest PIREPs
     */
    protected function recalculateAircraftStats()
    {
        $this->comment('--- RECALCULATING AIRCRAFT STATS ---');

        /** @var AircraftService $aircraftSvc */
        $aircraftSvc = app(AircraftService::class);
        $aircraftSvc->recalculateStats();
    }

    /**
     * Clear the value store of any old value mappings
     */
    protected function clearValueStore()
    {
        $this->idMapper->clear();
    }
}
