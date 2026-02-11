<?php

namespace App\Filament\Resources\UserFields;

use App\Filament\Resources\UserFields\Pages\ManageUserFields;
use App\Filament\Resources\UserFields\Schemas\UserFieldForm;
use App\Filament\Resources\UserFields\Tables\UserFieldsTable;
use App\Models\UserField;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class UserFieldResource extends Resource
{
    protected static ?string $model = UserField::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return UserFieldForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserFieldsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUserFields::route('/'),
        ];
    }

    public static function getModelLabel(): string
    {
        return trans_choice('common.user_field', 1);
    }
}
