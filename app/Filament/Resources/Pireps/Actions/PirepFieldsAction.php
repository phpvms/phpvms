<?php

namespace App\Filament\Resources\Pireps\Actions;

use App\Filament\Resources\PirepFields\PirepFieldResource;
use App\Models\PirepField;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class PirepFieldsAction
{
    public static function make(): Action
    {
        return Action::make('pirepfields')
            ->label(trans_choice('common.pirep_field', 2))
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->url(PirepFieldResource::getUrl('index'))
            ->visible(fn (): bool => auth()->user()?->can('view-any', PirepField::class));
    }
}
