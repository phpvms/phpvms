<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\Aircraft;
use App\Models\Enums\PirepSource;
use App\Models\Enums\PirepState;
use App\Support\Units\Time;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PirepsRelationManager extends RelationManager
{
    protected static string $relationship = 'pireps';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('ident')->label('Flight Ident'),
                TextColumn::make('dpt_airport_id')->label('DEP'),
                TextColumn::make('arr_airport_id')->label('ARR'),
                TextColumn::make('flight_time')->formatStateUsing(fn (int $state): string => Time::minutesToTimeString($state)),
                TextColumn::make('aircraft')->label('Aircraft')->formatStateUsing(fn (Aircraft $state): string => $state->registration.' \''.$state->name.'\''),
                TextColumn::make('level')->label('Flight Level'),
                TextColumn::make('source')->label('Filed using')->formatStateUsing(fn (int $state): string => PirepSource::label($state)),
                TextColumn::make('created_at')->label('Filed at')->date('d/m/Y H:i'),
                TextColumn::make('state')->badge()->color(fn (int $state): string => match ($state) {
                    PirepState::PENDING  => 'warning',
                    PirepState::ACCEPTED => 'success',
                    PirepState::REJECTED => 'danger',
                    default              => 'info',
                })->formatStateUsing(fn (int $state): string => PirepState::label($state)),
            ])
            ->filters([
                //
            ])
            ->headerActions([
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
            ]);
    }
}
