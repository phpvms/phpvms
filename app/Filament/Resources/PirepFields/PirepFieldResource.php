<?php

declare(strict_types=1);

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

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return PirepFieldForm::configure($schema);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return PirepFieldsTable::configure($table);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManagePirepFields::route('/'),
        ];
    }

    #[\Override]
    public static function getModelLabel(): string
    {
        return trans_choice('common.pirep_field', 1);
    }
}
