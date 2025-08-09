<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Filament\Resources\UserResource;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject_type')
                    ->formatStateUsing(fn (Activity $record): string => class_basename($record->subject_type).' '.$record->event)
                    ->sortable()
                    ->searchable()
                    ->label(__('common.action')),

                TextColumn::make('causer_type')
                    ->formatStateUsing(function (Activity $record): string {
                        if (class_basename($record->causer_type) === 'User') {
                            return $record->causer_id.' | '.$record->causer->name_private;
                        }

                        return $record->causer_id.' | '.class_basename($record->causer_type);
                    })
                    ->url(fn (Activity $record): ?string => $record->causer_type === 'App\Models\User' ? UserResource::getUrl('edit', ['record' => $record->causer_id]) : null)
                    ->sortable()
                    ->searchable()
                    ->label(__('activities.causer')),

                TextColumn::make('created_at')
                    ->sortable()
                    ->label(__('common.date'))
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make()
                    ->color('primary'),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
