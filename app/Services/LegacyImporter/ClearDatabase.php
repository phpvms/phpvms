<?php

namespace App\Services\LegacyImporter;

use App\Models\Acars;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Bid;
use App\Models\Expense;
use App\Models\File;
use App\Models\Flight;
use App\Models\FlightField;
use App\Models\FlightFieldValue;
use App\Models\Journal;
use App\Models\JournalTransaction;
use App\Models\Ledger;
use App\Models\News;
use App\Models\Pirep;
use App\Models\Subfleet;
use App\Models\User;
use App\Models\UserAward;
use Illuminate\Support\Facades\DB;

class ClearDatabase extends BaseImporter
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
                'message'  => 'Clearing database',
            ],
        ];
    }

    public function run($start = 0): void
    {
        $this->cleanupDb();
    }

    /**
     * Cleanup the local database of any users and other data that might conflict
     * before running the importer
     */
    protected function cleanupDb()
    {
        $this->info('Running database cleanup/empty before starting');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        Bid::truncate();
        File::truncate();
        News::truncate();

        Expense::truncate();
        JournalTransaction::truncate();
        Journal::truncate();
        Ledger::truncate();

        // Clear flights
        DB::table('flight_fare')->truncate();
        DB::table('flight_subfleet')->truncate();
        FlightField::truncate();
        FlightFieldValue::truncate();
        Flight::truncate();
        Subfleet::truncate();
        Aircraft::truncate();

        Airline::truncate();
        Airport::truncate();
        Acars::truncate();
        Pirep::truncate();

        UserAward::truncate();
        User::truncate();

        // Clear permissions
        DB::table('permission_role')->truncate();
        DB::table('permission_user')->truncate();
        DB::table('role_user')->truncate();

        // Role::truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->idMapper->clear();
    }
}
