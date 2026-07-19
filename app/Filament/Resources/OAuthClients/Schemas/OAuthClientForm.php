<?php

declare(strict_types=1);

namespace App\Filament\Resources\OAuthClients\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class OAuthClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('oauth.client_name'))
                    ->required()
                    ->maxLength(255),

                // Grant type is fixed at creation time; hidden when editing.
                Select::make('client_type')
                    ->label(__('oauth.client_type'))
                    ->live()
                    ->default('authorization_code')
                    ->options([
                        'authorization_code' => __('oauth.type_authorization_code'),
                        'pkce'               => __('oauth.type_pkce'),
                        'client_credentials' => __('oauth.type_client_credentials'),
                    ])
                    ->helperText(__('oauth.client_type_hint'))
                    ->visibleOn('create')
                    ->required(),

                TagsInput::make('redirect_uris')
                    ->label(__('oauth.redirect_uris'))
                    ->placeholder('https://example.com/callback')
                    ->helperText(__('oauth.redirect_uris_hint'))
                    ->visible(fn (Get $get, string $operation): bool => $operation === 'edit'
                        ? true
                        : in_array($get('client_type'), ['authorization_code', 'pkce'], true))
                    // authorization_code / PKCE clients are invalid without a
                    // redirect URI, so require at least one when creating them.
                    ->required(fn (Get $get, string $operation): bool => $operation === 'create'
                        && in_array($get('client_type'), ['authorization_code', 'pkce'], true)),

                Toggle::make('revoked')
                    ->label(__('oauth.revoked'))
                    ->helperText(__('oauth.revoked_hint'))
                    ->visibleOn('edit'),
            ])
            ->columns(1);
    }
}
