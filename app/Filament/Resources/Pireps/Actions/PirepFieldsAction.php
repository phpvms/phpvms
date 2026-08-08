<?php

namespace App\Filament\Resources\Pireps\Actions;

use App\Filament\Resources\PirepFields\PirepFieldResource;
use App\Models\PirepField;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;

class PirepFieldsAction
{
    public static function make(): Action
    {
        return Action::make('pirepfields')
            ->label(trans_choice('common.pirep_field', 2))
            /* Utility link, not a CTA — neutral field like the view page's
             * buttons; primary fill stays reserved for Create-style actions. */
            ->color('gray')
            ->icon(TablerIcon::ClipboardList)
            ->url(PirepFieldResource::getUrl('index'))
            ->visible(fn (): bool => auth()->user()?->can('view-any', PirepField::class));
    }
}
