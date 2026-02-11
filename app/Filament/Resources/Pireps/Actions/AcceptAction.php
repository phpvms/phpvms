<?php

namespace App\Filament\Resources\Pireps\Actions;

use App\Models\Enums\PirepState;
use App\Models\Pirep;
use App\Services\PirepService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class AcceptAction
{
    public static function make(): Action
    {
        return Action::make('accept')
            ->color('success')
            ->icon(Heroicon::CheckCircle)
            ->label(__('common.accept'))
            ->visible(fn (Pirep $record): bool => ($record->state === PirepState::PENDING || $record->state === PirepState::REJECTED))
            ->action(function (Pirep $record): void {
                $pirep = app(PirepService::class)->changeState($record, PirepState::ACCEPTED);
                if ($pirep->state === PirepState::ACCEPTED) {
                    Notification::make()
                        ->title(__('pireps.pirep_accepted'))
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title(__('pireps.error_changing_state'))
                        ->danger()
                        ->send();
                }
            });
    }
}
