<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Tables;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('permissions_count')
                    ->label(trans_choice('common.permission', 2))
                    ->counts('permissions')
                    ->badge(),

                IconColumn::make('disable_activity_checks')
                    ->label(__('filament.disable_activity_checks'))
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (Role $record): bool => RoleResource::isSuperAdmin($record)),
            ]);
    }
}
