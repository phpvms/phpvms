<?php

namespace App\Filament\Resources\FlightBundles\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FlightBundleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.bundles.sections.details'))
                    ->schema(self::fields())
                    ->columnSpanFull()
                    ->columns(1),
            ]);
    }

    /**
     * The bundle's editable fields, bare of layout — the create page wraps
     * them in a section, the edit drawer lays them out flat.
     *
     * @return array<int, Component>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->label(__('filament.bundles.fields.name')),

            Textarea::make('description')
                ->rows(3)
                ->label(__('filament.bundles.fields.description')),

            DatePicker::make('start_date')
                ->native(false)
                ->label(__('common.start_date'))
                ->helperText(__('filament.bundles.fields.start_date_helper')),

            DatePicker::make('end_date')
                ->native(false)
                ->afterOrEqual('start_date')
                ->label(__('common.end_date'))
                ->helperText(__('filament.bundles.fields.end_date_helper')),

            self::subfleets(),

            Toggle::make('enabled')
                ->default(true)
                ->label(__('filament.bundles.fields.enabled'))
                ->extraFieldWrapperAttributes(['class' => 'field-rule-above']),
        ];
    }

    public static function subfleets(): Select
    {
        return Select::make('subfleets')
            ->label(trans_choice('common.subfleet', 2))
            ->relationship('subfleets', 'name')
            ->multiple()
            ->searchable()
            ->preload();
    }
}
