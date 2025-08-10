<?php

namespace App\Filament\Resources\Pireps\Actions;

use App\Filament\Resources\PirepFields\PirepFieldResource;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class PirepFieldsAction
{
    public static function make(): Action
    {
        return Action::make('pirepfields')
            ->label(__('pireps.fields'))
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->url(PirepFieldResource::getUrl('index'))
            ->visible(fn (): bool => auth()->user()?->can('view_any_pirep::field'));
    }
}
