<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Concerns\IsDynamicDashboardWidget;
use App\Services\VersionService;
use Filament\Widgets\Widget;
use MDDev\DynamicDashboard\Contracts\DynamicWidget;
use Override;

class VersionWidget extends Widget implements DynamicWidget
{
    use IsDynamicDashboardWidget;

    protected string $view = 'filament.widgets.version-widget';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 0;

    protected string $version;

    protected string $version_full;

    public static function getWidgetLabel(): string
    {
        return __('filament.dashboard.version');
    }

    public static function getDynamicDashboardDefaultWidth(): int
    {
        return 6;
    }

    public static function getDynamicDashboardDefaultHeight(): int
    {
        return 2;
    }

    public function mount(VersionService $versionSvc): void
    {
        $this->version = $versionSvc->getCurrentVersion(false);
        $this->version_full = $versionSvc->getCurrentVersion(true);
    }

    #[Override]
    protected function getViewData(): array
    {
        return [
            'version'      => $this->version,
            'version_full' => $this->version_full,
        ];
    }
}
