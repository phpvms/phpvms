<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PirepState;
use Override;

class ReportsFiledStatWidget extends DashboardStatWidget
{
    public static function getWidgetLabel(): string
    {
        return __('filament.dashboard.reports_filed');
    }

    public static function getDynamicDashboardDefaultWidth(): int
    {
        return 2;
    }

    #[Override]
    protected function getViewData(): array
    {
        $reports = $this->recentReports();

        return [
            'label'    => static::getWidgetLabel(),
            'value'    => number_format((clone $reports)->count()),
            'suffix'   => null,
            'note'     => (clone $reports)->where('state', PirepState::ACCEPTED)->count().' accepted · '.(clone $reports)->where('state', PirepState::PENDING)->count().' pending',
            'icon'     => 'phosphor-file-text-light',
            'accent'   => 'violet',
            'noteIcon' => null,
        ];
    }
}
