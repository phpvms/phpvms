<?php

namespace App\Filament\Resources\PirepFields\Tables;

use App\Models\Enums\PirepFieldSource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PirepFieldsTable
{
    public static function configure(Table $table): Table
    {

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label(__('common.description')),

                TextColumn::make('pirep_source')
                    ->label(__('pireps.source'))
                    ->formatStateUsing(fn (int $state): string => PirepFieldSource::label($state))
                    ->sortable(),

                IconColumn::make('required')
                    ->label(__('common.required'))
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('pirep_source')
                    ->label(__('pireps.source'))
                    ->options(PirepFieldSource::labels()),
            ])
            ->recordActions([
                EditAction::make(),
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
