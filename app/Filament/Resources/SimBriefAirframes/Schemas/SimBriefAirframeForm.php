<?php

namespace App\Filament\Resources\SimBriefAirframes\Schemas;

use App\Models\Enums\AirframeSource;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SimBriefAirframeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('icao')
                    ->label('ICAO')
                    ->required()
                    ->string(),

                TextInput::make('name')
                    ->label(__('common.name'))
                    ->required()
                    ->string(),

                TextInput::make('airframe_id')
                    ->label(__('common.simbrief_airframe_id'))
                    ->string(),

                Hidden::make('source')
                    ->label(__('pireps.source'))
                    ->visibleOn('create')
                    ->formatStateUsing(fn () => AirframeSource::INTERNAL),
            ])
            ->columns(3);
    }
}
