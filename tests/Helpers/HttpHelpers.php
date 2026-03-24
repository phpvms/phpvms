<?php

use App\Contracts\Unit;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Carbon;

/**
 * Read a file from the data directory
 */
function readDataFile(string $filename): false|string
{
    $paths = [
        'data/'.$filename,
        'tests/data/'.$filename,
    ];

    foreach ($paths as $p) {
        if (file_exists($p)) {
            return file_get_contents($p);
        }
    }

    return false;
}

/**
 * Mock out the Guzzle HTTP client and return the given files as a single response
 */
function mockGuzzleResponse(array|string $files, string $contentType): void
{
    if (!is_array($files)) {
        $files = [$files];
    }

    $responses = [];
    foreach ($files as $file) {
        $responses[] = new Response(200, [
            'Content-Type' => $contentType,
        ], readDataFile($file));
    }

    $mock = new MockHandler($responses);

    $handler = HandlerStack::create($mock);
    $guzzleClient = new Client(['handler' => $handler]);
    app()->instance(Client::class, $guzzleClient);
}

/**
 * Transform any data that's passed in. E.g, make sure that any mutator
 * classes (e.g, units) are not passed in as the mutator class
 */
function transformData(array &$data): array
{
    foreach ($data as $key => &$value) {
        if (is_array($value)) {
            transformData($value);
        }

        if ($value instanceof DateTimeImmutable) {
            $data[$key] = $value->format(DATE_ATOM);
        } elseif ($value instanceof Carbon) {
            $data[$key] = $value->toIso8601ZuluString();
        } elseif ($value instanceof Unit) {
            $data[$key] = (float) $value->internal(2);
        }
    }

    return $data;
}

/**
 * Authenticate as the given user for the api
 */
function apiAs(User $user): void
{
    test()->withHeader('Authorization', $user->api_key);
}
