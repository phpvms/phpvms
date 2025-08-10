<?php

namespace App\Filament\Resources\PirepFields;

use App\Filament\Resources\PirepFields\Pages\ManagePirepFields;
use App\Filament\Resources\PirepFields\Schemas\PirepFieldForm;
use App\Filament\Resources\PirepFields\Tables\PirepFieldsTable;
use App\Models\PirepField;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PirepFieldResource extends Resource
{
    protected static ?string $model = PirepField::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PirepFieldForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PirepFieldsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePirepFields::route('/'),
        ];
    }

    public static function getModelLabel(): string
    {
        return trans_choice('common.field', 1);
    }
}
