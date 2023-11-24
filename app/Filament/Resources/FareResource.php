<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FareResource\Pages;
use App\Models\Enums\FareType;
use App\Models\Fare;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FareResource extends Resource
{
    protected static ?string $model = Fare::class;

    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Fares';
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Fare Information')
                    ->description('When a fare is assigned to a subfleet, the price, cost and capacity can be overridden, so you can create default values that will apply to most of your subfleets, and change them where they will differ.')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->hint('How this fare class will show up on a ticket')
                            ->required()
                            ->string(),

                        Forms\Components\TextInput::make('name')
                            ->hint('The fare class name, E.g "Economy" or "First"')
                            ->required()
                            ->string(),

                        Forms\Components\Select::make('type')
                            ->hint('If this is a passenger or cargo fare')
                            ->options(FareType::labels())
                            ->native(false)
                            ->required(),

                        Forms\Components\Toggle::make('active')
                            ->offIcon('heroicon-m-x-circle')
                            ->offColor('danger')
                            ->onIcon('heroicon-m-check-circle')
                            ->onColor('success')
                            ->default(true),
                    ])->columns(3),
                Forms\Components\Section::make('Base Fare Finances')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->hint('This is the price of a ticket or price per kg')
                            ->numeric(),

                        Forms\Components\TextInput::make('cost')
                            ->hint('The operating cost per unit (passenger or kg)')
                            ->numeric(),

                        Forms\Components\TextInput::make('capacity')
                            ->hint('Max seats or capacity available. This can be adjusted in the subfleet')
                            ->numeric(),

                        Forms\Components\RichEditor::make('notes')
                            ->hint('Any notes about this fare'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->formatStateUsing(fn (int $state): string => FareType::label($state)),

                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->money(setting('units.currency')),

                Tables\Columns\TextColumn::make('cost')
                    ->label('Cost')
                    ->money(setting('units.currency')),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes'),

                Tables\Columns\IconColumn::make('active')
                    ->label('Active')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->icon('heroicon-o-plus-circle')
                    ->label('Add Fare'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
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
            'index'  => Pages\ListFares::route('/'),
            'create' => Pages\CreateFare::route('/create'),
            'edit'   => Pages\EditFare::route('/{record}/edit'),
        ];
    }
}
