<?php

declare(strict_types=1);

use App\Features\Dashboard\DashboardWidget;
use App\Features\Dashboard\DashboardWidgets;

/**
 * Helpers shared by the dashboard widget tests. Required by each file, so a
 * parallel run that loads one file alone still has them.
 */
function registerWidgets(DashboardWidget ...$widgets): void
{
    foreach ($widgets as $widget) {
        app(DashboardWidgets::class)->register($widget->id, fn (): DashboardWidget => $widget);
    }
}

function gridWidget(string $id, bool $default = true, int $order = 100, string $zone = 'grid'): DashboardWidget
{
    return DashboardWidget::livewire($id, 'x::'.$id, $id, 'star', zone: $zone, default: $default, order: $order);
}
