<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PirepFieldResource\Pages\ManagePirepFields;
use App\Models\Enums\PirepFieldSource;
use App\Models\PirepField;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PirepFieldResource extends Resource
{
    protected static ?string $model = PirepField::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->string()
                    ->required(),

                TextInput::make('description')
                    ->string(),

                Select::make('pirep_source')
                    ->options(PirepFieldSource::select())
                    ->native(false)
                    ->required(),

                Toggle::make('required')
                    ->inline(false)
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
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description'),

                TextColumn::make('pirep_source')
                    ->formatStateUsing(fn (int $state): string => PirepFieldSource::label($state))
                    ->sortable(),

                IconColumn::make('required')
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
                    ->label('Add Pirep Field'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePirepFields::route('/'),
        ];
    }
}
