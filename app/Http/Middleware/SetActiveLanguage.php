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
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse) $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $preferredLanguage = 'en';
        if (setting('general.auto_language_detection', false) && !$request->hasCookie('lang')) {
            $preferredLanguage = $request->getPreferredLanguage(array_keys(config('languages')));
        } else {
            $preferredLanguage = $request->cookie('lang', config('app.locale', 'en'));
        }

        App::setLocale($preferredLanguage);

        return $next($request);
    }
}
