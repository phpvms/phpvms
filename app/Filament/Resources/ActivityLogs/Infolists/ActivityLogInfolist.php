<?php

namespace App\Filament\Resources\ActivityLogs\Infolists;

use App\Filament\Resources\UserResource;
use Carbon\Carbon;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Activitylog\Models\Activity;

class ActivityLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('activities.causer_information'))
                    ->schema([
                        TextEntry::make('causer_type')
                            ->formatStateUsing(fn (string $state): string => class_basename($state))
                            ->label(__('activities.causer_type')),

                        TextEntry::make('causer_id')
                            ->formatStateUsing(function (Activity $record): string {
                                if (class_basename($record->causer_type) === 'User') {
                                    return $record->causer_id.' | '.$record->causer->name_private;
                                }

                                return $record->causer_id.' | '.class_basename($record->causer_type);
                            })
                            ->url(fn (Activity $record): ?string => $record->causer_type === 'App\Models\User' ? UserResource::getUrl('edit', ['record' => $record->causer_id]) : null)
                            ->label(trans('activities.causer')),

                        TextEntry::make('created_at')
                            ->formatStateUsing(callback: fn (Carbon $state): string => $state->diffForHumans().' | '.$state->format('d.M'))
                            ->label(__('common.date')),
                    ])
                    ->columnSpanFull()
                    ->columns(3),

                Section::make(__('activities.subject_information'))
                    ->schema([
                        TextEntry::make('subject_type')
                            ->formatStateUsing(fn (string $state): string => class_basename($state))
                            ->label(__('activities.subject_type')),

                        TextEntry::make('subject_id')
                            ->label(__('activities.subject_id')),

                        TextEntry::make('subject.name')
                            ->placeholder('N/A')
                            ->label(__('common.name')),

                        TextEntry::make('event')
                            ->label(__('activities.event_type')),
                    ])
                    ->columnSpanFull()
                    ->columns(4),
            ]);
    }
}
