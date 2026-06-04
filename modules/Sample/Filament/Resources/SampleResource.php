<?php

declare(strict_types=1);

namespace Modules\Sample\Filament\Resources;

use App\Enums\NavigationGroup;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Sample\Filament\Resources\SampleResource\Pages\ListSampleItems;
use Modules\Sample\Models\SampleTable;

class SampleResource extends Resource
{
    protected static ?string $model = SampleTable::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::AddOns;

    protected static ?string $navigationLabel = 'Sample Items';

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
            ]);
    }

    #[\Override]
    public static function getRelations(): array
    {
        return [];
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ListSampleItems::route('/'),
        ];
    }
}
