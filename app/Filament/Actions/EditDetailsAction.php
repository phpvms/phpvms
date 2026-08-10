<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\EditAction;
use Filament\Schemas\Components\Component;

/**
 * The branded settings drawer for overview pages (Tailwind Plus
 * "drawer with branded header": md width, accent header styled via
 * .drawer-branded in the admin theme, flat single-column fields). Rendered
 * inside the overview's last card by the x-admin.overview component
 * (App\View\Components\Admin\Overview), which takes the action as its
 * edit-action prop — pages conventionally expose it as editAction().
 *
 * Pages chain their own ->modalHeading(), ->modalDescription() and
 * ->extraModalFooterActions() (e.g. a DeleteAction) on top.
 */
class EditDetailsAction
{
    /**
     * @param array<int, Component> $fields
     */
    public static function make(array $fields): EditAction
    {
        return Drawer::configure(
            EditAction::make()
                ->label(__('common.edit'))
                ->icon(TablerIcon::Pencil)
                ->color('gray'),
            $fields,
        );
    }
}
