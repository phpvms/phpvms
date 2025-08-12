<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;

class SetActiveLanguage
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):((Response|RedirectResponse)) $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $preferredLanguage = 'en';
        if ((setting('general.auto_language_detection', false) && !$request->hasCookie('lang')) || request()->is('system/install')) {
            $preferredLanguage = $request->getPreferredLanguage(array_keys(config('languages')));
        } else {
            $preferredLanguage = $request->cookie('lang', config('app.locale', 'en'));
        }

        App::setLocale($preferredLanguage);

        return $next($request);
    }
}
