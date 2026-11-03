<?php

declare(strict_types=1);

use App\Support\HttpClient;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * connect_timeout alone only bounds the handshake: a server that accepted the
 * connection and then answered slowly held the request for as long as it liked.
 */
function httpClientRecordingOptions(array &$seen): HttpClient
{
    $handler = function (RequestInterface $request, array $options) use (&$seen): FulfilledPromise {
        $seen[] = $options;

        return new FulfilledPromise(new Response(200, ['Content-Type' => 'text/plain'], 'ok'));
    };

    return new HttpClient(new Client(['handler' => HandlerStack::create($handler)]));
}

it('bounds every request by a total timeout', function (): void {
    $seen = [];
    $client = httpClientRecordingOptions($seen);

    $client->get('https://example.test/metar');
    $client->post('https://example.test/json', ['a' => 1]);
    $client->form_post('https://example.test/form', ['a' => 1]);

    expect($seen)->toHaveCount(3);
    foreach ($seen as $options) {
        expect($options['timeout'] ?? 0)->toBeGreaterThan(0)
            ->and($options['connect_timeout'])->toBe(2);
    }
});

it('lets the caller choose its own timeout', function (): void {
    $seen = [];
    httpClientRecordingOptions($seen)->get('https://example.test/slow', ['timeout' => 30]);

    expect($seen[0]['timeout'])->toBe(30);
});
