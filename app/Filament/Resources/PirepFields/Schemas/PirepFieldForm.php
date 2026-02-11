<?php

namespace App\Filament\Resources\PirepFields\Schemas;

use App\Models\Enums\PirepFieldSource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PirepFieldForm
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

                Select::make('pirep_source')
                    ->label(__('pireps.source'))
                    ->options(PirepFieldSource::select())
                    ->native(false)
                    ->required(),

                Toggle::make('required')
                    ->label(__('common.required'))
                    ->inline(false)
                    ->offIcon(Heroicon::XCircle)
                    ->offColor('danger')
                    ->onIcon(Heroicon::CheckCircle)
                    ->onColor('success'),
            ]);
    }
}
