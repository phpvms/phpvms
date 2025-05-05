<?php

namespace App\Support;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\RequestOptions;

/**
 * Helper for HTTP stuff
 */
class HttpClient
{
    private GuzzleClient $httpClient;

    public function __construct(GuzzleClient $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Download a URI. If a file is given, it will save the downloaded
     * content into that file
     *
     *
     * @return string
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function get($uri, array $opts = [])
    {
        $opts = array_merge([
            'connect_timeout' => 2, // wait two seconds by default
        ], $opts);

        $response = $this->httpClient->request('GET', $uri, $opts);

        $body = $response->getBody()->getContents();
        $content_type = $response->getHeaderLine('content-type');
        if (strpos($content_type, 'application/json') !== false) {
            $body = \GuzzleHttp\json_decode($body, true);
        }

        return $body;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function post($uri, $body, array $opts = []): mixed
    {
        $opts = array_merge([
            'connect_timeout'    => 2,
            RequestOptions::JSON => $body,
        ], $opts);

        $response = $this->httpClient->post($uri, $opts);
        $content_type = $response->getHeaderLine('content-type');
        $content = $response->getBody()->getContents();

        if (str_contains($content_type, 'application/json')) {
            $content = \GuzzleHttp\json_decode($content, true);
        }

        return $content;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function form_post($uri, $body, array $opts = []): mixed
    {
        $opts = array_merge([
            'connect_timeout' => 2,
            'form_params'     => $body,
        ], $opts);

        $response = $this->httpClient->request('POST', $uri, $opts);
        $content_type = $response->getHeaderLine('content-type');
        $content = $response->getBody()->getContents();

        if (str_contains($content_type, 'application/json')) {
            $content = \GuzzleHttp\json_decode($content, true);
        }

        return $content;
    }

    /**
     * Download a file to a given path
     *
     *
     * @return string
     */
    public function download($uri, $local_path)
    {
        $opts = [];
        if ($local_path !== null) {
            $opts['sink'] = $local_path;
        }

        $response = $this->httpClient->request('GET', $uri, $opts);

        $body = $response->getBody()->getContents();
        if ($response->getHeader('content-type') === 'application/json') {
            $body = \GuzzleHttp\json_decode($body);
        }

        return $body;
    }
}
