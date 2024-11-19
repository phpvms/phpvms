<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetActiveLanguage
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request                                                                          $request
     * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse) $next
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $preferredLanguage = 'en';
        if ($request->hasCookie('lang')) {
            $preferredLanguage = $request->cookie('lang', config('app.locale', 'en'));
        } else {
            $preferredLanguage = $request->getPreferredLanguage(array_keys(config('languages')));
        }

        App::setLocale($preferredLanguage);

        return $next($request);
    }
}
