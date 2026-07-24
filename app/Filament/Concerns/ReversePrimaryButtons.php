<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

/**
 * Flips a form's action row so the escape action comes first and the primary
 * action comes last.
 *
 * Filament builds these the other way round -- save first, cancel last (see
 * EditRecord::getFormActions() and CreateRecord::getFormActions()) -- which
 * puts the primary action furthest from the edge of the row:
 *
 *   before:  [ Save changes ] [ Cancel ]
 *   after:   [ Cancel ] [ Save changes ]
 *
 * Filament has no global hook for this: the order is just the array literal
 * each page returns, so every page that wants it calls this from its own
 * getFormActions(). Doing it explicitly (rather than having a trait silently
 * override getFormActions) keeps it visible at the call site and lets a page
 * add its own actions without losing the ordering.
 *
 * @see EditRecord::getFormActions()
 */
trait ReversePrimaryButtons
{
    /**
     * @param  array<Action> $actions
     * @return array<Action>
     */
    protected function reversePrimaryButtons(array $actions): array
    {
        return array_reverse($actions);
    }
}
