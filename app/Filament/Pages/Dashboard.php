<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\AuthorizesAccess;
use App\Http\Middleware\UpdatePending;
use Filament\Pages\Dashboard as FilamentDashboard;

class Dashboard extends FilamentDashboard
{
    use AuthorizesAccess;

    protected static string|array $routeMiddleware = [UpdatePending::class];
}
