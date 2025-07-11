<?php

namespace App\Console\Commands;

use App\Contracts\Command;
use App\Models\Acars;
use App\Models\Airline;
use App\Models\Pirep;
use App\Models\User;
use App\Notifications\Messages\UserRegistered;
use App\Repositories\AcarsRepository;
use App\Services\AirportService;
use App\Services\AwardService;
use App\Services\DatabaseService;
use App\Services\UserService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Symfony\Component\Yaml\Yaml;

class DevCommands extends Command
{
    protected $signature = 'phpvms {cmd} {param?}';

    protected $description = 'Developer commands';

    /**
     * DevCommands constructor.
     */
    public function __construct(protected DatabaseService $dbSvc)
    {
        parent::__construct();
    }

    /**
     * Run dev related commands
     */
    public function handle(): void
    {
        $command = trim($this->argument('cmd'));

        if ($command === '' || $command === '0') {
            $this->error('No command specified!');
            exit;
        }

        $commands = [
            'clear-acars'       => 'clearAcars',
            'clear-users'       => 'clearUsers',
            'compile-assets'    => 'compileAssets',
            'db-attrs'          => 'dbAttrs',
            'list-awards'       => 'listAwardClasses',
            'live-flights'      => 'liveFlights',
            'manual-insert'     => 'manualInsert',
            'metar'             => 'getMetar',
            'recalculate-stats' => 'recalculateStats',
            'reset-install'     => 'resetInstall',
            'new-user-email'    => 'newUserEmail',
            'xml-to-yaml'       => 'xmlToYaml',
        ];

        if (!array_key_exists($command, $commands)) {
            $this->error('Command not found!');
            exit;
        }

        $this->{$commands[$command]}();
    }

    /**
     * List all award classes
     */
    protected function listAwardClasses()
    {
        $awardSvc = app(AwardService::class);
        $awards = $awardSvc->findAllAwardClasses();

        $headers = ['Award Name', 'Class'];
        $formatted_awards = [];
        foreach ($awards as $award) {
            $formatted_awards[] = [$award->name, $award::class];
        }

        $this->table($headers, $formatted_awards);
    }

    /**
     * Delete all the data from the ACARS and PIREP tables
     */
    protected function clearAcars()
    {
        if (config('database.default') === 'mysql') {
            DB::statement('SET foreign_key_checks=0');
        }

        Acars::truncate();
        Pirep::truncate();

        if (config('database.default') === 'mysql') {
            DB::statement('SET foreign_key_checks=1');
        }

        $this->info('ACARS and PIREPs cleared!');
    }

    /**
     * Delete all the data from the ACARS and PIREP tables
     */
    protected function clearUsers()
    {
        if (config('database.default') === 'mysql') {
            DB::statement('SET foreign_key_checks=0');
        }

        DB::statement('TRUNCATE `role_user`');
        Airline::truncate();
        User::truncate();

        if (config('database.default') === 'mysql') {
            DB::statement('SET foreign_key_checks=1');
        }

        $this->info('Users cleared!');
    }

    /**
     * Compile all the CSS/JS assets into their respective files
     * Calling the webpack compiler
     */
    protected function compileAssets()
    {
        $this->runCommand('npm update');
        $this->runCommand('npm run dev');
    }

    /**
     * Output DB prepares versions
     */
    protected function dbAttrs()
    {
        $pdo = DB::connection()->getPdo();
        $emulate_prepares_below_version = '5.1.17';
        $server_version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        $emulate_prepares = version_compare($server_version, $emulate_prepares_below_version, '<');

        $this->info('Server Version: '.$server_version);
        $this->info('Emulate Prepares: '.$emulate_prepares);

        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulate_prepares);
    }

    /**
     * Convert the sequelpro xml export to yaml
     */
    protected function xmlToYaml()
    {
        $file = $this->argument('param');
        $this->info('Reading '.$file);

        $xml_str = file_get_contents($file);
        $xml = new \SimpleXMLElement($xml_str);

        $yaml = [];
        $table_name = (string) $xml->database->table_data['name'];
        $this->info('Writing table "'.$table_name.'"');

        $count = 0;
        $yaml[$table_name] = [];

        foreach ($xml->database->table_data->row as $row) {
            $yaml_row = [];
            foreach ($row->field as $field) {
                $fname = (string) $field['name'];
                $fvalue = (string) $field;

                $yaml_row[$fname] = $fvalue;
            }

            $yaml[$table_name][] = $yaml_row;
            $count++;
        }

        $this->info('Exporting '.$count.' rows');

        $file_name = $table_name.'.yml';
        file_put_contents(storage_path($file_name), Yaml::dump($yaml, 4, 2));
        $this->info('Writing yaml to storage: '.$file_name);
    }

    protected function getMetar(): void
    {
        $icao = $this->argument('param');
        if (!$icao) {
            $this->error('Enter an ICAO!');
            exit;
        }

        $airportSvc = app(AirportService::class);
        $metar = $airportSvc->getMetar($icao);
        $this->info($metar->raw);
    }

    /**
     * Insert the rows from the file, manually advancing each row
     */
    protected function manualInsert(): void
    {
        $file = $this->argument('param');
        $this->info('Reading '.$file);

        if (!file_exists($file)) {
            $this->error('File '.$file.' doesn\'t exist');
            exit;
        }

        $yml = Yaml::parse(file_get_contents($file));
        foreach ($yml as $table => $rows) {
            $this->info('Importing table '.$table);
            $this->info('Number of rows: '.\count($rows));

            foreach ($rows as $row) {
                try {
                    $this->dbSvc->insert_row($table, $row);
                } catch (\Exception $e) {
                    $this->error($e);
                }

                $this->confirm('Insert next row?', true);
            }
        }
    }

    /**
     * Recalculate the stats for all users
     */
    protected function recalculateStats(): void
    {
        $userSvc = app(UserService::class);
        $userSvc->recalculateAllUserStats();
    }

    /**
     * Delete all of the tables, etc from the database, for a clean install
     */
    protected function resetInstall(): void
    {
        $confirm = $this->ask('This will erase your entire install and database, are you sure? y/n ');
        if (strtolower((string) $confirm) !== 'y') {
            exit(0);
        }

        try {
            if (config('database.default') === 'mysql') {
                DB::statement('SET foreign_key_checks=0');
            }

            $this->info('Dropping all tables');
            $tables = DB::connection()->getDoctrineSchemaManager()->listTableNames();
            foreach ($tables as $table) {
                Schema::dropIfExists($table);
            }
        } catch (QueryException $e) {
            $this->error('DB error: '.$e->getMessage());
        }

        $this->info('Deleting config file');

        try {
            unlink('config.php');
        } catch (\Exception) {
        }

        $this->info('Deleting env file');

        try {
            unlink('env.php');
        } catch (\Exception) {
        }

        $this->info('Clearing caches');
        Artisan::call('cache:clear');
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');

        $this->info('Done!');
    }

    /**
     * Test sending a user a registered email
     */
    protected function newUserEmail()
    {
        $user_id = $this->argument('param');
        $user = User::find($user_id);
        $user->notify(new UserRegistered($user));
    }

    public function liveFlights(): void
    {
        $acarsRepo = app(AcarsRepository::class);
        $flights = $acarsRepo->getPositions(setting('acars.live_time'))->toArray();

        dd($flights);
    }
}
