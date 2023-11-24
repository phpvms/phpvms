<?php

namespace App\Filament\Pages;

use App\Http\Middleware\UpdatePending;
use Filament\Pages\Dashboard as FilamentDashboard;

class Dashboard extends FilamentDashboard
{
    protected static string | array $routeMiddleware = [UpdatePending::class];
}
