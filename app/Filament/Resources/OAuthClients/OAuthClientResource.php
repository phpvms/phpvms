<?php

declare(strict_types=1);

namespace App\Filament\Resources\OAuthClients;

use App\Enums\NavigationGroup;
use App\Filament\Resources\OAuthClients\Pages\CreateOAuthClient;
use App\Filament\Resources\OAuthClients\Pages\EditOAuthClient;
use App\Filament\Resources\OAuthClients\Pages\ListOAuthClients;
use App\Filament\Resources\OAuthClients\Schemas\OAuthClientForm;
use App\Filament\Resources\OAuthClients\Tables\OAuthClientsTable;
use App\Models\OauthClient;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

class OAuthClientResource extends Resource
{
    protected static ?string $model = OauthClient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Developers;

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return OAuthClientForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return OAuthClientsTable::configure($table);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index'  => ListOAuthClients::route('/'),
            'create' => CreateOAuthClient::route('/create'),
            'edit'   => EditOAuthClient::route('/{record}/edit'),
        ];
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return __('oauth.client');
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return __('oauth.clients');
    }
}
