<?php

namespace App\Filament\Widgets;

use App\Services\VersionService;
use Filament\Widgets\Widget;

class VersionWidget extends Widget
{
    protected static string $view = 'filament.widgets.version-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 0;

    protected string $version;

    protected string $version_full;

    public function mount(
        VersionService $versionSvc
    ): void {
        $this->version = $versionSvc->getCurrentVersion(false);
        $this->version_full = $versionSvc->getCurrentVersion(true);
    }

    protected function getViewData(): array
    {
        return [
            'version'      => $this->version,
            'version_full' => $this->version_full,
        ];
    }
}
