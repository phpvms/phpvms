<?php

namespace App\Filament\Resources\UserFields\Schemas;

use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserFieldForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('common.name'))
                    ->string()
                    ->required(),

                TextInput::make('description')
                    ->label(__('common.description'))
                    ->string(),

                Toggle::make('required')
                    ->label(__('common.required'))
                    ->offIcon(TablerIcon::X)
                    ->offColor('danger')
                    ->onIcon(TablerIcon::Check)
                    ->onColor('success'),

                Toggle::make('show_on_registration')
                    ->label(__('filament.user_field_show_on_registration'))
                    ->offIcon(TablerIcon::X)
                    ->offColor('danger')
                    ->onIcon(TablerIcon::Check)
                    ->onColor('success'),

                Toggle::make('private')
                    ->label(__('filament.user_field_private'))
                    ->offIcon(TablerIcon::X)
                    ->offColor('danger')
                    ->onIcon(TablerIcon::Check)
                    ->onColor('success'),

                Toggle::make('active')
                    ->label(__('common.active'))
                    ->offIcon(TablerIcon::X)
                    ->offColor('danger')
                    ->onIcon(TablerIcon::Check)
                    ->onColor('success'),
            ]);
    }
}
