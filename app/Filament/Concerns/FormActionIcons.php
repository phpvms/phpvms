<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;

/**
 * Orders a form's action row primary-last, and gives each button an icon.
 *
 * Filament builds the row save-first, cancel-last (see
 * `EditRecord::getFormActions()`), which puts the primary action furthest from
 * the edge of the row:
 *
 *   before:  [ Save changes ] [ Cancel ]
 *   after:   [ Cancel ] [ Save changes ]
 *
 * The order is just the array literal each page returns, so pages call
 * `reversePrimaryButtons()` from their own `getFormActions()`. Doing it
 * explicitly rather than overriding `getFormActions()` keeps it visible at the
 * call site and lets a page add its own actions without losing the order.
 *
 * Split out from [[ReversePrimaryButtons]] because this half works on any page,
 * while that trait's footer placement only works on a resource page. Settings
 * is a plain `Filament\Pages\Page` and needs this half alone.
 */
trait FormActionIcons
{
    /**
     * @param  array<Action> $actions
     * @return array<Action>
     */
    protected function reversePrimaryButtons(array $actions): array
    {
        foreach ($actions as $action) {
            $action->icon(self::formActionIcon($action->getName()));
        }

        return array_reverse($actions);
    }

    /**
     * Filament builds its form actions without icons, which leaves them a few
     * pixels shorter than any button that has one -- visible as soon as they
     * share a row with something like "Run test". Tabler throughout, matching
     * the rest of the panel.
     */
    private static function formActionIcon(string $name): ?TablerIcon
    {
        return match ($name) {
            'save'               => TablerIcon::DeviceFloppy,
            'create'             => TablerIcon::Plus,
            'createAnother'      => TablerIcon::CopyPlus,
            'createReturnFlight' => TablerIcon::ArrowsExchange,
            'cancel'             => TablerIcon::X,
            default              => null,
        };
    }
}
