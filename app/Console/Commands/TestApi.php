<?php

namespace App\Console\Commands;

use App\Contracts\Command;
use GuzzleHttp\Client;

class TestApi extends Command
{
    protected $signature = 'phpvms:test-api {apikey} {url}';

    protected Client $httpClient;

    /**
     * Run dev related commands
     */
    public function handle(): void
    {
        $this->httpClient = new Client([
            'headers' => [
                'Authorization' => $this->argument('apikey'),
                'Content-type'  => 'application/json',
                'X-API-Key'     => $this->argument('apikey'),
            ],
        ]);

        $result = $this->httpClient->get($this->argument('url'));
        echo $result->getBody();
    }
}
