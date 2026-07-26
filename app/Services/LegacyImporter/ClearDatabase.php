<?php

declare(strict_types=1);

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
use Illuminate\Support\Facades\Schema;
use Override;

class ClearDatabase extends BaseImporter
{
    /**
     * Returns a default manifest just so this step gets run
     */
    #[Override]
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

        // MySQL refuses to TRUNCATE any table a foreign key points at, even
        // when the referencing table holds no rows, so the constraints are
        // suspended and children are cleared before parents.
        // Schema::withoutForeignKeyConstraints() rather than the bare
        // `SET FOREIGN_KEY_CHECKS` this used to issue: that statement is a
        // syntax error on the SQLite and Postgres targets the importer also
        // writes to, and it left the checks off for the rest of the
        // connection when a truncate in between threw.
        Schema::withoutForeignKeyConstraints(function (): void {
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

            // Every one of these is reachable only through a subfleet, and
            // `subfleets.id` is an auto-increment this truncate resets: a row
            // left behind does not dangle, it renames itself onto whichever
            // freshly imported subfleet lands on its old id. A bundle's
            // subfleet defaults cannot outlive the subfleets they name, and
            // neither can a fare override, a rank grant or a type rating --
            // ImportService::importSubfleets() clears the same set.
            DB::table('bundle_subfleet')->truncate();
            DB::table('subfleet_fare')->truncate();
            DB::table('subfleet_rank')->truncate();
            DB::table('typerating_subfleet')->truncate();
            Subfleet::truncate();

            Aircraft::truncate();

            Airline::truncate();
            Airport::truncate();
            Acars::truncate();
            Pirep::truncate();

            UserAward::truncate();
            User::truncate();

            // Clear permissions. These are the spatie/laravel-permission
            // pivots: the laratrust tables this used to name
            // (`permission_role`, `permission_user`, `role_user`) were dropped
            // by the v7 -> v8 migration, so every run of this importer died
            // here on "no such table". Both are keyed by `users.id`, another
            // auto-increment reset above -- a row left behind would grant a
            // freshly imported pilot the roles of the one who held that id.
            //
            // `role_has_permissions` is deliberately absent: roles and
            // permissions are seeded configuration, not imported data, and
            // neither table is truncated here.
            DB::table('model_has_roles')->truncate();
            DB::table('model_has_permissions')->truncate();

            // Role::truncate();
        });

        $this->idMapper->clear();
    }
}
