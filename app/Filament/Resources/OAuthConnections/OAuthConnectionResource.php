<?php

declare(strict_types=1);

namespace App\Filament\Resources\OAuthConnections;

use App\Enums\NavigationGroup;
use App\Filament\Resources\OAuthConnections\Pages\ManageOAuthConnections;
use App\Filament\Resources\OAuthConnections\Schemas\OAuthConnectionForm;
use App\Filament\Resources\OAuthConnections\Tables\OAuthConnectionsTable;
use App\Models\OAuthConnection;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Override;
use UnitEnum;

class OAuthConnectionResource extends Resource
{
    protected static ?string $model = OAuthConnection::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Config;

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Phosphor::LinkLight;

    protected static ?string $recordTitleAttribute = 'display_name';

    protected static ?string $slug = 'social-login';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return OAuthConnectionForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return OAuthConnectionsTable::configure($table);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageOAuthConnections::route('/'),
        ];
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return __('social_login.connection');
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return __('social_login.connections');
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('social_login.title');
    }
}
