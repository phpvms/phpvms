<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget as FilamentAccountWidget;
use MDDev\DynamicDashboard\Concerns\HasEmptySettings;
use MDDev\DynamicDashboard\Concerns\HasSizeDefaults;
use MDDev\DynamicDashboard\Contracts\DynamicWidget;

class AccountWidget extends FilamentAccountWidget implements DynamicWidget
{
    use HasEmptySettings;
    use HasSizeDefaults;

    public static function getWidgetLabel(): string
    {
        return __('filament.dashboard.account');
    }

    /** @return list<class-string> */
    public static function availableForDashboard(): array
    {
        return [Dashboard::class];
    }

    public static function getDynamicDashboardDefaultWidth(): int
    {
        return 6;
    }

    public static function getDynamicDashboardDefaultHeight(): int
    {
        return 2;
    }
}
