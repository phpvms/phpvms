<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserFieldResource\Pages\ManageUserFields;
use App\Models\UserField;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserFieldResource extends Resource
{
    protected static ?string $model = UserField::class;

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

                Toggle::make('required')
                    ->offIcon('heroicon-m-x-circle')
                    ->offColor('danger')
                    ->onIcon('heroicon-m-check-circle')
                    ->onColor('success'),

                Toggle::make('show_on_registration')
                    ->offIcon('heroicon-m-x-circle')
                    ->offColor('danger')
                    ->onIcon('heroicon-m-check-circle')
                    ->onColor('success'),

                Toggle::make('private')
                    ->label('Private (Only Visible To Admins)')
                    ->offIcon('heroicon-m-x-circle')
                    ->offColor('danger')
                    ->onIcon('heroicon-m-check-circle')
                    ->onColor('success'),

                Toggle::make('active')
                    ->offIcon('heroicon-m-x-circle')
                    ->offColor('danger')
                    ->onIcon('heroicon-m-check-circle')
                    ->onColor('success'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('internal', false))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description'),

                IconColumn::make('required')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->sortable(),

                IconColumn::make('show_on_registration')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->sortable(),

                IconColumn::make('private')
                    ->label('Private (Only Visible To Admins)')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->sortable(),

                IconColumn::make('active')
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
                    ->label('Add User Field'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUserFields::route('/'),
        ];
    }
}
