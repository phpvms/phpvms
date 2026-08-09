<?php

declare(strict_types=1);

namespace App\Filament\Resources\OAuthClients\Tables;

use App\Models\OauthClient;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OAuthClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('oauth.client_name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label(__('oauth.client_type'))
                    ->badge()
                    ->state(fn (OauthClient $record): string => $record->confidential()
                        ? __('oauth.confidential')
                        : __('oauth.public_pkce'))
                    ->color(fn (OauthClient $record): string => $record->confidential() ? 'warning' : 'info'),

                TextColumn::make('grant_types')
                    ->label(__('oauth.grant_types'))
                    ->badge()
                    ->separator(','),

                IconColumn::make('revoked')
                    ->label(__('oauth.revoked'))
                    ->boolean()
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),

                TextColumn::make('created_at')
                    ->label(__('common.created_at'))
                    ->since()
                    ->dateTooltip('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
