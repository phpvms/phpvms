<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogResource\Pages\ViewActivityLog;
use Carbon\Carbon;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Config';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Activities';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Causer Information')
                    ->schema([
                        TextEntry::make('causer_type')->formatStateUsing(fn (string $state): string => class_basename($state)),
                        TextEntry::make('causer_id')
                            ->formatStateUsing(function (Activity $record): string {
                                if (class_basename($record->causer_type) === 'User') {
                                    return $record->causer_id.' | '.$record->causer->name_private;
                                }

                                return $record->causer_id.' | '.class_basename($record->causer_type);
                            })
                            ->url(fn (Activity $record): ?string => $record->causer_type === 'App\Models\User' ? UserResource::getUrl('edit', ['record' => $record->causer_id]) : null)
                            ->label('Causer'),
                        TextEntry::make('created_at')->formatStateUsing(fn (Carbon $state): string => $state->diffForHumans().' | '.$state->format('d.M'))->label('Caused'),
                    ])->columns(3),

                Section::make('Subject Information')
                    ->schema([
                        TextEntry::make('subject_type')->formatStateUsing(fn (string $state): string => class_basename($state)),
                        TextEntry::make('subject_id'),
                        TextEntry::make('subject.name')->placeholder('N/A'),
                        TextEntry::make('event')->label('Event Type'),
                    ])->columns(4),

                Section::make('Changes')
                    ->schema([
                        ViewEntry::make('changes')
                            ->view('filament.infolists.entries.activity-fields'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject_type')
                    ->formatStateUsing(fn (Activity $record): string => class_basename($record->subject_type).' '.$record->event)
                    ->sortable()
                    ->searchable()
                    ->label('Action'),

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
                    ->label('Causer'),

                TextColumn::make('created_at')
                    ->sortable()
                    ->label('Date')
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make()->color('primary'),
            ])
            ->toolbarActions([
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
            'index' => ListActivityLogs::route('/'),
            'view'  => ViewActivityLog::route('/{record}'),
        ];
    }
}
