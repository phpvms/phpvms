<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Services\VersionService;

class DashboardWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-widget';
    protected int | string | array $columnSpan = 'full';
    protected string $version;
    protected string $version_full;

    public function mount(
        VersionService $versionSvc
    ) {
        $this->version = $versionSvc->getCurrentVersion(false);
        $this->version_full = $versionSvc->getCurrentVersion(true);
    }

    protected function getViewData(): array
    {
        return [
            'version' => $this->version,
            'version_full' => $this->version_full,
        ];
    }
}
