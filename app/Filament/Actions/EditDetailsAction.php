<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\EditAction;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;

/**
 * The branded settings drawer for summary-strip pages (Tailwind Plus
 * "drawer with branded header": md width, accent header styled via
 * .drawer-branded in the admin theme, flat single-column fields). Rendered
 * inside the strip's last card by filament/shared/summary-strip.blade.php,
 * which expects the page method to be named editAction().
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
        return EditAction::make()
            ->label(__('common.edit'))
            ->icon(TablerIcon::Pencil)
            ->color('gray')
            ->slideOver()
            ->modalWidth(Width::Medium)
            ->extraModalWindowAttributes(['class' => 'drawer-branded'])
            ->schema(fn (Schema $schema): Schema => $schema
                ->components($fields)
                ->columns(1));
    }
}
