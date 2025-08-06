<?php

namespace App\Filament\Resources\FlightResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class FieldValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'field_values';

    protected static ?string $title = 'Fields';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->string()
                    ->maxLength(255),

                TextInput::make('value')
                    ->required()
                    ->string()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name'),
                TextInputColumn::make('value'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()->label('Add Flight Field')
                    ->mutateDataUsing(function (array $data): array {
                        $data['flight_id'] = $this->getOwnerRecord()->id;
                        $data['slug'] = Str::slug($data['name']);

                        return $data;
                    }),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
