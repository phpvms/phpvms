<?php

namespace App\Filament\Resources\Pireps\Actions;

use App\Models\Enums\PirepState;
use App\Models\Pirep;
use App\Services\PirepService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class RejectAction
{
    public static function make(): Action
    {
        return Action::make('reject')
            ->color('danger')
            ->icon(Heroicon::XCircle)
            ->label(__('common.reject'))
            ->visible(fn (Pirep $record): bool => ($record->state === PirepState::PENDING || $record->state === PirepState::ACCEPTED))
            ->action(function (Pirep $record): void {
                $pirep = app(PirepService::class)->changeState($record, PirepState::REJECTED);
                if ($pirep->state === PirepState::REJECTED) {
                    Notification::make()
                        ->title(__('pireps.pirep_rejected'))
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
