<?php

namespace App\Filament\Resources\Typeratings\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TyperatingForm
{
    public static function configure(Schema $schema): Schema
    {

        return $schema
            ->components([
                Section::make(__('filament.typerating_information'))->schema([
                    TextInput::make('name')
                        ->label(__('common.name'))
                        ->required(),

                    TextInput::make('type')
                        ->label(__('common.type'))
                        ->required(),

                    TextInput::make('description')
                        ->label(__('common.description')),

                    TextInput::make('image_url')
                        ->label(__('common.image_url')),

                    Toggle::make('active')
                        ->label(__('common.active'))
                        ->offIcon('tabler-x')
                        ->offColor('danger')
                        ->onIcon('tabler-check')
                        ->onColor('success'),
                ])
                    ->columnSpanFull()
                    ->columns(),
            ]);
    }
}
