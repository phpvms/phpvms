<?php

namespace App\Filament\Resources\FlightBundles\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FlightBundleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.bundles.sections.details'))
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label(__('filament.bundles.fields.name')),

                        Textarea::make('description')
                            ->rows(3)
                            ->label(__('filament.bundles.fields.description')),

                        Toggle::make('enabled')
                            ->default(true)
                            ->label(__('filament.bundles.fields.enabled')),

                        DatePicker::make('start_date')
                            ->native(false)
                            ->label(__('common.start_date'))
                            ->helperText(__('filament.bundles.fields.start_date_helper')),

                        DatePicker::make('end_date')
                            ->native(false)
                            ->afterOrEqual('start_date')
                            ->label(__('common.end_date'))
                            ->helperText(__('filament.bundles.fields.end_date_helper')),
                    ])
                    ->columns(2),
            ]);
    }
}
