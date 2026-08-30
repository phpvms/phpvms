<?php

declare(strict_types=1);

namespace App\Filament\Resources\OAuthConnections\Tables;

use App\Features\OAuth\Helpers\OAuthConnectionService;
use App\Features\OAuth\Helpers\SocialiteProviderRegistry;
use App\Filament\Actions\Drawer;
use App\Filament\Resources\OAuthConnections\Schemas\OAuthConnectionForm;
use App\Models\OAuthConnection;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OAuthConnectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('display_name')
                    ->label(__('social_login.connection'))
                    ->description(fn (OAuthConnection $record): string => $record->connection_id)
                    ->searchable(['display_name', 'connection_id'])
                    ->sortable(),

                TextColumn::make('provider')
                    ->label(__('social_login.columns.provider'))
                    ->state(fn (OAuthConnection $record): string => self::providerDefinition($record)['label'] ?? $record->provider)
                    ->description(fn (OAuthConnection $record): string => self::providerDefinition($record)['package'] ?? ''),

                TextColumn::make('provider_status')
                    ->label(__('social_login.columns.package'))
                    ->state(fn (OAuthConnection $record): string => self::registry()->isInstalled($record->provider)
                        ? __('social_login.status.available')
                        : __('social_login.status.unavailable'))
                    ->badge()
                    ->color(fn (OAuthConnection $record): string => self::registry()->isInstalled($record->provider) ? 'success' : 'danger'),

                TextColumn::make('managed_by')
                    ->label(__('social_login.columns.management'))
                    ->state(fn (OAuthConnection $record): string => $record->managed_by === null
                        ? __('social_login.status.manual')
                        : __('social_login.status.managed'))
                    ->badge()
                    ->color(fn (OAuthConnection $record): string => $record->managed_by === null ? 'gray' : 'info'),

                IconColumn::make('enabled')
                    ->label(__('common.enabled'))
                    ->boolean()
                    ->sortable(),

                IconColumn::make('login_enabled')
                    ->label(__('social_login.columns.login'))
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('registration_enabled')
                    ->label(__('social_login.columns.registration'))
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('linking_enabled')
                    ->label(__('social_login.columns.linking'))
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('sort_order')
                    ->label(__('social_login.columns.order'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ActionGroup::make([
                    Drawer::configure(
                        EditAction::make()
                            ->using(fn (OAuthConnection $record, array $data): OAuthConnection => app(OAuthConnectionService::class)->update(
                                $record,
                                OAuthConnectionForm::prepareData($data, $record),
                            )),
                        OAuthConnectionForm::fields(),
                    ),

                    Action::make('enable')
                        ->label(__('social_login.actions.enable'))
                        ->icon(Phosphor::CheckCircleLight)
                        ->color('success')
                        ->visible(fn (OAuthConnection $record): bool => !$record->enabled)
                        ->disabled(fn (OAuthConnection $record): bool => !self::registry()->isInstalled($record->provider))
                        ->action(fn (OAuthConnection $record): OAuthConnection => app(OAuthConnectionService::class)->enable($record)),

                    Action::make('disable')
                        ->label(__('social_login.actions.disable'))
                        ->icon(Phosphor::XCircleLight)
                        ->color('warning')
                        ->visible(fn (OAuthConnection $record): bool => $record->enabled)
                        ->requiresConfirmation()
                        ->modalDescription(__('social_login.actions.disable_confirm'))
                        ->action(fn (OAuthConnection $record): OAuthConnection => app(OAuthConnectionService::class)->disable($record)),

                    DeleteAction::make()
                        ->disabled(fn (OAuthConnection $record): bool => $record->managed_by !== null)
                        ->tooltip(fn (OAuthConnection $record): ?string => $record->managed_by === null
                            ? null
                            : __('social_login.actions.managed_delete'))
                        ->using(fn (OAuthConnection $record): bool => self::delete($record)),
                ]),
            ])
            ->emptyStateHeading(__('social_login.empty_heading'))
            ->emptyStateDescription(__('social_login.empty_description'));
    }

    private static function delete(OAuthConnection $record): bool
    {
        app(OAuthConnectionService::class)->delete($record);

        return true;
    }

    private static function providerDefinition(OAuthConnection $record): ?array
    {
        return self::registry()->find($record->provider);
    }

    private static function registry(): SocialiteProviderRegistry
    {
        return app(SocialiteProviderRegistry::class);
    }
}
