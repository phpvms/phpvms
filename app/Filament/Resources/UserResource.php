<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Filament\Resources\UserResource\Widgets\UserStats;
use App\Models\Enums\UserState;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    public static function getNavigationBadge(): ?string
    {
        return User::where('state', UserState::PENDING)->count() > 0
            ? User::where('state', UserState::PENDING)->count()
            : null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ident')->label('ID')->searchable(query: function (Builder $query, int $search): Builder {
                    return $query
                        ->where('pilot_id', "{$search}");
                }),
                TextColumn::make('callsign')->label('Callsign')->searchable(),
                TextColumn::make('name')->label('Name')->searchable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('created_at')->label('Registered On')->dateTime('d-m-Y'),
                TextColumn::make('state')->badge()->color(fn (int $state): string => match ($state) {
                    UserState::PENDING => 'warning',
                    UserState::ACTIVE => 'success',
                    default => 'info',
                })->formatStateUsing(fn (int $state): string => UserState::label($state)),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
            ]);
    }
    
    public static function getRelations(): array
    {
        return [
            RelationManagers\FieldsRelationManager::class
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }    

    public static function getWidgets(): array
    {
        return [
            UserStats::class,
        ];
    }
}
