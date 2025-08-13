<?php

namespace App\Filament\Resources\Invites\Tables;

use App\Models\Invite;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('token')
                    ->badge()
                    ->label(__('common.type'))
                    ->formatStateUsing(fn (Invite $record): string => is_null($record->email) ? __('common.link') : __('common.email'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('link')
                    ->label(__('invites.email_or_link'))
                    ->placeholder(__('invites.copy_link'))
                    ->copyable(fn (Invite $record): bool => is_null($record->email)),

                TextColumn::make('usage_count')
                    ->label(__('invites.usage_count'))
                    ->sortable(),

                TextColumn::make('usage_limit')
                    ->label(__('invites.usage_limit'))
                    ->placeholder(__('invites.no_limit'))
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label(__('common.expires'))
                    ->placeholder(__('common.never'))
                    ->since()
                    ->dateTooltip('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
