<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Filament\Pages\Dashboard;
use MDDev\DynamicDashboard\Concerns\HasEmptySettings;
use MDDev\DynamicDashboard\Concerns\HasSizeDefaults;

trait IsDynamicDashboardWidget
{
    use HasEmptySettings;
    use HasSizeDefaults;

    /** @return list<class-string> */
    public static function availableForDashboard(): array
    {
        return [Dashboard::class];
    }
}
