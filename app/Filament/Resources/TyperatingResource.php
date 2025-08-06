<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TypeRatingResource\Pages\CreateTypeRating;
use App\Filament\Resources\TypeRatingResource\Pages\EditTypeRating;
use App\Filament\Resources\TypeRatingResource\Pages\ListTypeRatings;
use App\Filament\Resources\TypeRatingResource\RelationManagers\SubfleetsRelationManager;
use App\Models\Typerating;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TyperatingResource extends Resource
{
    protected static string|\UnitEnum|null $navigationGroup = 'Config';

    protected static ?int $navigationSort = 5;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rocket-launch';

    protected static ?string $navigationLabel = 'Type Ratings';

    protected static ?string $modelLabel = 'Type Ratings';

    protected static ?string $model = Typerating::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Type Rating Informations')->schema([
                    TextInput::make('name')
                        ->required(),

                    TextInput::make('type')
                        ->required(),

                    TextInput::make('description'),

                    TextInput::make('image_url'),

                    Toggle::make('active')
                        ->offIcon('heroicon-m-x-circle')
                        ->offColor('danger')
                        ->onIcon('heroicon-m-check-circle')
                        ->onColor('success')
                        ->default(true),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description'),

                TextColumn::make('image_url'),

                IconColumn::make('active')
                    ->label('Active')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->sortable(),
            ])
            ->filters([
                //
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
                CreateAction::make()
                    ->icon('heroicon-o-plus-circle')
                    ->label('Add Type Rating'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SubfleetsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListTypeRatings::route('/'),
            'create' => CreateTypeRating::route('/create'),
            'edit'   => EditTypeRating::route('/{record}/edit'),
        ];
    }
}
