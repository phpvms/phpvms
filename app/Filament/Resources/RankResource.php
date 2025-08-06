<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RankResource\Pages\CreateRank;
use App\Filament\Resources\RankResource\Pages\EditRank;
use App\Filament\Resources\RankResource\Pages\ListRanks;
use App\Filament\Resources\RankResource\RelationManagers\SubfleetsRelationManager;
use App\Models\Rank;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RankResource extends Resource
{
    protected static ?string $model = Rank::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Config';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Ranks';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Rank Informations')->schema([
                    Grid::make('')
                        ->schema([
                            TextInput::make('name')
                                ->required()
                                ->string(),

                            TextInput::make('image_url')
                                ->label('Image Link')
                                ->string(),
                        ])->columns(2),
                    Grid::make('')
                        ->schema([
                            TextInput::make('hours')
                                ->required()
                                ->numeric()
                                ->minValue(0),

                            TextInput::make('acars_base_pay_rate')
                                ->label('ACARS Base Pay Rate')
                                ->numeric()
                                ->minValue(0)
                                ->helperText('Base rate, per-flight hour, for ACARS PIREPs. Can be adjusted via a multiplier on the subfleet.'),

                            TextInput::make('manual_base_pay_rate')
                                ->label('Manual Base Pay Rate')
                                ->numeric()
                                ->minValue(0)
                                ->helperText('Base rate, per-flight hour, for manually-filed PIREPs. Can be adjusted via a multiplier on the subfleet.'),

                            Toggle::make('auto_approve_acars')
                                ->helperText('PIREPS submitted through ACARS are automatically accepted')
                                ->label('Auto Approve ACARS PIREPs')
                                ->offIcon('heroicon-m-x-circle')
                                ->offColor('danger')
                                ->onIcon('heroicon-m-check-circle')
                                ->onColor('success'),

                            Toggle::make('auto_approve_manual')
                                ->helperText('PIREPS submitted manually are automatically accepted')
                                ->label('Auto Approve Manual PIREPs')
                                ->offIcon('heroicon-m-x-circle')
                                ->offColor('danger')
                                ->onIcon('heroicon-m-check-circle')
                                ->onColor('success'),

                            Toggle::make('auto_promote')
                                ->helperText('When a pilot reaches these hours, they\'ll be upgraded to this rank')
                                ->label('Auto Promote')
                                ->offIcon('heroicon-m-x-circle')
                                ->offColor('danger')
                                ->onIcon('heroicon-m-check-circle')
                                ->onColor('success'),
                        ])->columns(3),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('hours')
                    ->label('Hours')
                    ->sortable(),

                IconColumn::make('auto_approve_acars')
                    ->label('Auto Approve Acars')
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->icon(fn ($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->sortable(),

                IconColumn::make('auto_approve_manual')
                    ->label('Auto Approve Manual')
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->icon(fn ($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->sortable(),

                IconColumn::make('auto_promote')
                    ->label('Auto Promote')
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->icon(fn ($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->sortable(),
            ])
            ->defaultSort('hours')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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
                    ->label('Add Airport'),
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
            'index'  => ListRanks::route('/'),
            'create' => CreateRank::route('/create'),
            'edit'   => EditRank::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
