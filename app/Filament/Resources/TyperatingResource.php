<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TypeRatingResource\Pages;
use App\Models\Typerating;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TyperatingResource extends Resource
{
    protected static ?string $navigationGroup = 'config';

    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';

    protected static ?string $navigationLabel = 'type ratings';

    protected static ?string $modelLabel = 'Type Ratings';

    protected static ?string $model = Typerating::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->label('Name'),
                Forms\Components\TextInput::make('type')
                    ->required()
                    ->label('Type'),
                Forms\Components\TextInput::make('description')
                    ->label('Description'),
                Forms\Components\TextInput::make('image_url')
                    ->label('Image URL'),
                Forms\Components\Checkbox::make('active')
                    ->label('Active'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('type'),
                Tables\Columns\TextColumn::make('description'),
                Tables\Columns\TextColumn::make('image_url'),
                Tables\Columns\TextColumn::make('active'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
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
            'index'  => Pages\ListTypeRatings::route('/'),
            'create' => Pages\CreateTypeRating::route('/create'),
            'edit'   => Pages\EditTypeRating::route('/{record}/edit'),
        ];
    }
}
