<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActivityChangesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make(name: 'field')
                    ->label(__('activities.field')),

                TextColumn::make(name: 'oldValue')
                    ->label(__('activities.old_value')),

                TextColumn::make(name: 'newValue')
                    ->label(__('activities.new_value')),
            ]);
    }
}
