<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strip remote host auth/headers from the Log Viewer hosts response
 */
class StripLogViewerHostCredentials
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$response instanceof JsonResponse || $request->route()?->getName() !== 'log-viewer.hosts') {
            return $response;
        }

        $payload = $response->getData(true);
        if (!is_array($payload)) {
            return $response;
        }

        $hosts = array_key_exists('data', $payload) ? $payload['data'] : $payload;
        if (!is_array($hosts)) {
            return $response;
        }

        $hosts = array_map(fn (array $host): array => array_diff_key($host, ['auth' => 1, 'headers' => 1]), $hosts);

        if (array_key_exists('data', $payload)) {
            $payload['data'] = $hosts;
        } else {
            $payload = $hosts;
        }

        return $response->setData($payload);
    }
}
