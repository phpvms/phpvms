<?php

namespace App\Filament\Resources\Pireps\Actions;

use App\Models\Pirep;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class ViewAction
{
    public static function make(): Action
    {
        return Action::make('view')
            ->color('info')
            ->icon(Heroicon::Eye)
            ->label(__('pireps.view_pirep'))
            ->url(fn (Pirep $record): string => route('frontend.pireps.show', $record->id))
            ->openUrlInNewTab();
    }
}
