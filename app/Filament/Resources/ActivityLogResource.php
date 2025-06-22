<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use Carbon\Carbon;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationGroup = 'Config';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Activities';

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Causer Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('causer_type')->formatStateUsing(fn (string $state): string => class_basename($state)),
                        Infolists\Components\TextEntry::make('causer_id')
                            ->formatStateUsing(function (Activity $record): string {
                                if (class_basename($record->causer_type) === 'User') {
                                    return $record->causer_id.' | '.$record->causer->name_private;
                                }

                                return $record->causer_id.' | '.class_basename($record->causer_type);
                            })
                            ->url(fn (Activity $record): ?string => $record->causer_type === \App\Models\User::class ? UserResource::getUrl('edit', ['record' => $record->causer_id]) : null)
                            ->label('Causer'),
                        Infolists\Components\TextEntry::make('created_at')->formatStateUsing(fn (Carbon $state): string => $state->diffForHumans().' | '.$state->format('d.M'))->label('Caused'),
                    ])->columns(3),

                Infolists\Components\Section::make('Subject Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('subject_type')->formatStateUsing(fn (string $state): string => class_basename($state)),
                        Infolists\Components\TextEntry::make('subject_id'),
                        Infolists\Components\TextEntry::make('subject.name')->placeholder('N/A'),
                        Infolists\Components\TextEntry::make('event')->label('Event Type'),
                    ])->columns(4),

                Infolists\Components\Section::make('Changes')
                    ->schema([
                        Infolists\Components\ViewEntry::make('changes')
                            ->view('filament.infolists.entries.activity-fields'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subject_type')
                    ->formatStateUsing(fn (Activity $record): string => class_basename($record->subject_type).' '.$record->event)
                    ->sortable()
                    ->searchable()
                    ->label('Action'),

                Tables\Columns\TextColumn::make('causer_type')
                    ->formatStateUsing(function (Activity $record): string {
                        if (class_basename($record->causer_type) === 'User') {
                            return $record->causer_id.' | '.$record->causer->name_private;
                        }

                        return $record->causer_id.' | '.class_basename($record->causer_type);
                    })
                    ->url(fn (Activity $record): ?string => $record->causer_type === \App\Models\User::class ? UserResource::getUrl('edit', ['record' => $record->causer_id]) : null)
                    ->sortable()
                    ->searchable()
                    ->label('Causer'),

                Tables\Columns\TextColumn::make('created_at')
                    ->sortable()
                    ->label('Date')
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->color('primary'),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view'  => Pages\ViewActivityLog::route('/{record}'),
        ];
    }
}
