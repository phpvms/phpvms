<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RankResource\Pages;
use App\Filament\Resources\RankResource\RelationManagers;
use App\Models\Rank;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RankResource extends Resource
{
    protected static ?string $model = Rank::class;

    protected static ?string $navigationGroup = 'Config';
    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Rank';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Rank Informations')->schema([
                    Forms\Components\Grid::make('')
                        ->schema([
                            Forms\Components\TextInput::make('name')->required()->string()->maxLength(50),
                            Forms\Components\TextInput::make('image_url')->label('Image Link')->string()->maxLength(191),
                        ])->columns(2),
                    Forms\Components\Grid::make('')
                        ->schema([
                            Forms\Components\TextInput::make('hours')->required()->numeric()->minValue(0),
                            Forms\Components\TextInput::make('acars_base_pay_rate')->label('ACARS Base Pay Rate')->numeric()->minValue(0)->helperText('Base rate, per-flight hour, for ACARS PIREPs. Can be adjusted via a multiplier on the subfleet.'),
                            Forms\Components\TextInput::make('manual_base_pay_rate')->label('Manual Base Pay Rate')->numeric()->minValue(0)->helperText('Base rate, per-flight hour, for manually-filed PIREPs. Can be adjusted via a multiplier on the subfleet.'),

                            Forms\Components\Toggle::make('auto_approve_acars')->helperText('PIREPS submitted through ACARS are automatically accepted')->label('Auto Approve ACARS PIREPs')->offIcon('heroicon-m-x-circle')->offColor('danger')->onIcon('heroicon-m-check-circle')->onColor('success'),
                            Forms\Components\Toggle::make('auto_approve_manual')->helperText('PIREPS submitted manually are automatically accepted')->label('Auto Approve Manual PIREPs')->offIcon('heroicon-m-x-circle')->offColor('danger')->onIcon('heroicon-m-check-circle')->onColor('success'),
                            Forms\Components\Toggle::make('auto_promote')->helperText('When a pilot reaches these hours, they\'ll be upgraded to this rank')->label('Auto Promote')->offIcon('heroicon-m-x-circle')->offColor('danger')->onIcon('heroicon-m-check-circle')->onColor('success'),
                        ])->columns(3),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Name')->searchable(),
                Tables\Columns\TextColumn::make('hours')->label('Hours'),
                Tables\Columns\IconColumn::make('auto_approve_acars')->label('Auto Approve Acars')->color(fn ($state) => $state ? 'success' : 'danger')->icon(fn ($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                Tables\Columns\IconColumn::make('auto_approve_manual')->label('Auto Approve Manual')->color(fn ($state) => $state ? 'success' : 'danger')->icon(fn ($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                Tables\Columns\IconColumn::make('auto_promote')->label('Auto Promote')->color(fn ($state) => $state ? 'success' : 'danger')->icon(fn ($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
                    ->label('Add Airport'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SubfleetsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRanks::route('/'),
            'create' => Pages\CreateRank::route('/create'),
            'edit'   => Pages\EditRank::route('/{record}/edit'),
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
