<?php

/**
 * Set the content type in the API layer.
 */

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use Closure;
use Illuminate\Http\Request;

class JsonResponse implements Middleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('charset', 'utf-8');

        return $response;
    }
}
