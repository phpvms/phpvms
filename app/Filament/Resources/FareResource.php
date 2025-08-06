<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FareResource\Pages\CreateFare;
use App\Filament\Resources\FareResource\Pages\EditFare;
use App\Filament\Resources\FareResource\Pages\ListFares;
use App\Models\Enums\FareType;
use App\Models\Fare;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FareResource extends Resource
{
    protected static ?string $model = Fare::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Fares';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Fare Information')
                    ->description('When a fare is assigned to a subfleet, the price, cost and capacity can be overridden, so you can create default values that will apply to most of your subfleets, and change them where they will differ.')
                    ->schema([
                        TextInput::make('code')
                            ->hint('How this fare class will show up on a ticket')
                            ->required()
                            ->string(),

                        TextInput::make('name')
                            ->hint('The fare class name, E.g "Economy" or "First"')
                            ->required()
                            ->string(),

                        Select::make('type')
                            ->hint('If this is a passenger or cargo fare')
                            ->options(FareType::labels())
                            ->native(false)
                            ->required(),

                        Toggle::make('active')
                            ->offIcon('heroicon-m-x-circle')
                            ->offColor('danger')
                            ->onIcon('heroicon-m-check-circle')
                            ->onColor('success')
                            ->default(true),
                    ])->columns(3),
                Section::make('Base Fare Finances')
                    ->schema([
                        TextInput::make('price')
                            ->hint('This is the price of a ticket or price per kg')
                            ->numeric(),

                        TextInput::make('cost')
                            ->hint('The operating cost per unit (passenger or kg)')
                            ->numeric(),

                        TextInput::make('capacity')
                            ->hint('Max seats or capacity available. This can be adjusted in the subfleet')
                            ->numeric(),

                        RichEditor::make('notes')
                            ->hint('Any notes about this fare'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->formatStateUsing(fn (int $state): string => FareType::label($state))
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Price')
                    ->money(setting('units.currency'))
                    ->sortable(),

                TextColumn::make('cost')
                    ->label('Cost')
                    ->money(setting('units.currency'))
                    ->sortable(),

                TextColumn::make('notes')
                    ->label('Notes'),

                IconColumn::make('active')
                    ->label('Active')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                CreateAction::make()
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
            'index'  => ListFares::route('/'),
            'create' => CreateFare::route('/create'),
            'edit'   => EditFare::route('/{record}/edit'),
        ];
    }
}
