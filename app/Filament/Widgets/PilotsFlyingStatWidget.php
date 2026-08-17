<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\User;
use Override;

class PilotsFlyingStatWidget extends DashboardStatWidget
{
    public static function getWidgetLabel(): string
    {
        return __('filament.dashboard.pilots_flying');
    }

    public static function getDynamicDashboardDefaultWidth(): int
    {
        return 3;
    }

    #[Override]
    protected function getViewData(): array
    {
        $reports = $this->recentReports();
        $topPilot = (clone $reports)
            ->selectRaw('user_id, COUNT(*) as legs')
            ->groupBy('user_id')
            ->orderByDesc('legs')
            ->with('user:id,name')
            ->first();

        return [
            'label'    => static::getWidgetLabel(),
            'value'    => number_format((clone $reports)->distinct('user_id')->count('user_id')),
            'suffix'   => 'of '.number_format(User::active()->count()),
            'note'     => $topPilot?->user?->name !== null ? $topPilot->user->name.' leads on '.(int) $topPilot->getAttribute('legs').' legs' : null,
            'icon'     => 'phosphor-users-light',
            'accent'   => 'rose',
            'noteIcon' => null,
        ];
    }
}
