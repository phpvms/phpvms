<?php

namespace App\Filament\Resources\Pireps\Actions;

use App\Enums\PirepState;
use App\Models\Pirep;
use App\Services\PirepService;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class RejectAction
{
    public static function make(): Action
    {
        return Action::make('reject')
            ->color('danger')
            ->icon(Phosphor::XCircleLight)
            ->label(__('common.reject'))
            ->visible(fn (Pirep $record): bool => $record->state === PirepState::PENDING)
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
