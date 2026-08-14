<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;

/**
 * The branded drawer chrome shared by every slide-over editor (Tailwind Plus
 * "drawer with branded header": accent header styled via .drawer-branded in
 * the admin theme, flat single-column fields). Wraps any action — the
 * overview Edit drawers and the fact editor both route through here.
 */
class Drawer
{
    /**
     * @template TAction of Action
     *
     * @param  TAction               $action
     * @param  array<int, Component> $fields
     * @return TAction
     */
    public static function configure(Action $action, array $fields, Width $width = Width::Medium): Action
    {
        return $action
            ->slideOver()
            ->modalWidth($width)
            ->extraModalWindowAttributes(['class' => 'drawer-branded'])
            // Start alignment, not End: the vendor's End variant is
            // flex-row-reverse, which would flip the order below. The theme's
            // .drawer-branded rules justify the row to the end instead.
            ->modalFooterActionsAlignment(Alignment::Start)
            ->modalFooterActions(function (Action $action): array {
                // Vendor order is [submit, ...extras, cancel]; drawers want
                // [...extras, cancel, submit]. The last extra carries the auto
                // margin (.drawer-footer-start) that strands destructive
                // actions on the left of the footer.
                $extras = array_values($action->getExtraModalFooterActions());

                if ($extras !== []) {
                    end($extras)->extraAttributes(['class' => 'drawer-footer-start'], merge: true);
                }

                return [
                    ...$extras,
                    ...array_filter([
                        $action->getModalCancelAction(),
                        $action->getModalSubmitAction(),
                    ]),
                ];
            })
            ->schema(fn (Schema $schema): Schema => $schema
                ->components($fields)
                ->columns(1));
    }
}
