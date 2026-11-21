<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use App\Features\Setup\Actions\DetectPendingWork;
use App\Features\Setup\Support\UpgradeCompleted;
use Closure;
use Illuminate\Http\Request;

/**
 * Send every admin request to the upgrader until the 7.x upgrade is done.
 * Runs before Filament auth: its panel access check reads roles tables a 7.x database lacks.
 */
class UpgradeRequired implements Middleware
{
    public function __construct(
        private readonly DetectPendingWork $detect,
        private readonly UpgradeCompleted $completed,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        if (!$this->completed->exists() && $this->detect->handle()->blocksPanel()) {
            return redirect('/system/upgrade');
        }

        return $next($request);
    }
}
