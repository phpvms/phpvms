<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Actions\Action;

/**
 * Flips the order of a page's form actions so that Cancel comes first and the
 * primary action (Save/Create) comes last.
 *
 * Filament ships these the other way round -- save first, cancel last -- which
 * reads backwards once the row is right-aligned, because the primary action
 * ends up furthest from the edge of the screen. Reversing gives:
 *
 *   Edit:    [ Cancel ] [ Save changes ]
 *   Create:  [ Cancel ] [ Create & create another ] [ Create ]
 *
 * The alignment itself is set globally, see AppServiceProvider.
 */
trait PutsPrimaryActionLast
{
    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return array_reverse(parent::getFormActions());
    }
}
