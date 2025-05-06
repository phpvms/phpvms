<?php

namespace App\Console\Commands;

use App\Contracts\Command;
use App\Support\Units\Time;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Collection;

class AcarsReplay extends Command
{
    protected $signature = 'phpvms:replay {files} {--manual} {--write-all} {--no-submit}';

    protected $description = 'Replay an ACARS file';

    /**
     * API Key to post as
     */
    protected string $apiKey = 'testadminapikey';

    /**
     * For automatic updates, how many seconds to sleep between updates
     */
    protected int $sleepTime = 10;

    /**
     * @var array key == update[callsign]
     *            value == PIREP ID
     */
    protected array $pirepList = [];

    protected Client $httpClient;

    /**
     * Return an instance of an HTTP client all ready to post
     */
    public function __construct()
    {
        parent::__construct();

        $this->httpClient = new Client([
            'base_uri' => config('app.url'),
            'headers'  => [
                'Authorization' => $this->apiKey,
            ],
        ]);
    }

    /**
     * Make a request to start a PIREP
     *
     * @param \stdClass $flight
     *
     * @throws \RuntimeException
     */
    protected function startPirep($flight): string
    {
        // convert the planned flight time to be completely in minutes
        $pft = Time::hoursToMinutes(
            $flight->planned_hrsenroute,
            $flight->planned_minenroute
        );

        $flight_number = substr($flight->callsign, 3);

        $response = $this->httpClient->post('/api/pireps/prefile', [
            'json' => [
                'airline_id'          => 1,
                'flight_number'       => $flight_number,
                'aircraft_id'         => 1,
                'dpt_airport_id'      => $flight->planned_depairport,
                'arr_airport_id'      => $flight->planned_destairport,
                'level'               => $flight->planned_altitude,
                'planned_flight_time' => $pft,
                'route'               => $flight->planned_route,
            ],
        ]);

        $body = \json_decode($response->getBody()->getContents());

        return $body->id;
    }

    /**
     * Mark the PIREP as filed
     *
     *
     * @return mixed
     *
     * @throws \RuntimeException
     */
    protected function filePirep($pirep_id)
    {
        $response = $this->httpClient->post('/api/pireps/'.$pirep_id.'/file', [
            'json' => [],
        ]);

        $body = \json_decode($response->getBody()->getContents());

        return $body;
    }

    /**
     * @return array
     *
     * @throws \RuntimeException
     */
    protected function postUpdate($pirep_id, $data)
    {
        $uri = '/api/pireps/'.$pirep_id.'/acars/position';

        $position = [
            'log'          => '',
            'lat'          => $data->latitude,
            'lon'          => $data->longitude,
            'heading'      => $data->heading,
            'altitude'     => $data->altitude,
            'altitude_agl' => $data->altitude,
            'altitude_msl' => $data->altitude,
            'gs'           => $data->groundspeed,
            'transponder'  => $data->transponder,
        ];

        $upd = [
            'positions' => [
                $position,
            ],
        ];

        $this->info(
            "Update: $data->callsign, $position[lat] x $position[lon] \t\t"
            ."hdg: $position[heading]\t\talt: $position[altitude]\t\tgs: $position[gs]"
        );

        $response = $this->httpClient->post($uri, [
            'json' => $upd,
        ]);

        $body = \json_decode($response->getBody()->getContents());

        return [
            $data->callsign,
            $position['lat'],
            $position['lon'],
            $position['heading'],
            $position['altitude'],
            $position['altitude_agl'],
            $position['altitude_msl'],
            $position['gs'],
        ];
    }

    /**
     * Parse this file and run the updates
     *
     *
     * @throws \RuntimeException
     */
    protected function updatesFromFile(array $files)
    {
        /**
         * @var $flights Collection
         */
        $flights = collect($files)->transform(function ($f) {
            $file = $f;
            if (file_exists($file)) {
                $this->info('Loading '.$file);
                $contents = file_get_contents($file);
                $contents = \json_decode($contents);

                return collect($contents->updates);
            }
            $this->error($file.' not found, skipping');

            return false;
        })
            // remove any of errored file entries
            ->filter(function ($value, $key) {
                return $value !== false;
            });

        $this->info('Starting playback');

        /*
         * File the initial pirep to get a "preflight" status
         */
        $flights->each(function ($updates, $idx) {
            $update = $updates->first();
            $pirep_id = $this->startPirep($update);
            $this->pirepList[$update->callsign] = $pirep_id;
            $this->info('Prefiled '.$update->callsign.', ID: '.$pirep_id);
        });

        /*
         * Iterate through all of the flights, retrieving the updates
         * from each individual flight. Remove the update. Continue through
         * until there are no updates left, at which point we remove the flight
         * and updates.
         *
         * Continue until we have no more flights and updates left
         */
        while ($flights->count() > 0) {
            $flights = $flights->each(function ($updates, $idx) {
                $update = $updates->shift();
                $pirep_id = $this->pirepList[$update->callsign];

                $this->postUpdate($pirep_id, $update);

                // we're done and don't put the "no-submit" option
                if ($updates->count() === 0 && !$this->option('no-submit')) {
                    $this->filePirep($pirep_id);
                }
            })->filter(function ($updates, $idx) {
                return $updates->count() > 0;
            });

            if (!$this->option('write-all')) {
                if (!$this->option('manual')) {
                    sleep($this->sleepTime);
                } else {
                    $this->confirm('Send next batch of updates?', true);
                }
            }
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public function handle(): void
    {
        $files = $this->argument('files');
        $manual_mode = $this->option('manual');

        if ($this->option('write-all')) {
            $this->info('In "dump-all" mode, just writing it all in');
        } elseif (!$manual_mode) {
            /* @noinspection NestedPositiveIfStatementsInspection */
            $this->info('Going to send updates every 10s');
        } else {
            $this->info('In "manual advance" mode');
        }

        $this->updatesFromFile(explode(',', $files));
        $this->info('Done!');
    }
}
