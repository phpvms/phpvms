<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\Aircraft;
use App\Models\Enums\PirepSource;
use App\Models\Enums\PirepState;
use App\Support\Units\Time;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PirepsRelationManager extends RelationManager
{
    protected static string $relationship = 'pireps';

    public function form(Form $form): Form
    {
        return $form
            ->schema([

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('ident')->label('Flight Ident'),
                Tables\Columns\TextColumn::make('dpt_airport_id')->label('DEP'),
                Tables\Columns\TextColumn::make('arr_airport_id')->label('ARR'),
                Tables\Columns\TextColumn::make('flight_time')->formatStateUsing(fn (int $state): string => Time::minutesToTimeString($state)),
                Tables\Columns\TextColumn::make('aircraft')->label('Aircraft')->formatStateUsing(fn (Aircraft $state): string => $state->registration.' \''.$state->name.'\''),
                Tables\Columns\TextColumn::make('level')->label('Flight Level'),
                Tables\Columns\TextColumn::make('source')->label('Filed using')->formatStateUsing(fn (int $state): string => PirepSource::label($state)),
                Tables\Columns\TextColumn::make('created_at')->label('Filed at')->date('d/m/Y H:i'),
                Tables\Columns\TextColumn::make('state')->badge()->color(fn (int $state): string => match ($state) {
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
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
            ]);
    }
}
