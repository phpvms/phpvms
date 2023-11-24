<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PirepFieldResource\Pages;
use App\Models\PirepField;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PirepFieldResource extends Resource
{
    protected static ?string $model = PirepField::class;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->string()
                    ->required(),

                Forms\Components\TextInput::make('description')
                    ->string(),

                Forms\Components\Toggle::make('required')
                    ->offIcon('heroicon-m-x-circle')
                    ->offColor('danger')
                    ->onIcon('heroicon-m-check-circle')
                    ->onColor('success'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('description'),
                Tables\Columns\IconColumn::make('required')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->icon('heroicon-o-plus-circle')
                    ->label('Add Pirep Field'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePirepFields::route('/'),
        ];
    }
}
