<?php

namespace App\Filament\Resources\FlightResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FieldValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'field_values';

    protected static ?string $title = 'Fields';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->string()
                    ->maxLength(255),

                Forms\Components\TextInput::make('value')
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
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextInputColumn::make('value'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Add Flight Field')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['flight_id'] = $this->getOwnerRecord()->id;
                        $data['slug'] = \Illuminate\Support\Str::slug($data['name']);

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
