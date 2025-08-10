<?php

namespace App\Filament\Resources\Modules\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ModuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Edit Only (we are not using the default create action)
                Toggle::make('enabled')
                    ->label(__('common.enabled'))
                    ->offIcon(Heroicon::XCircle)
                    ->offColor('danger')
                    ->onIcon(Heroicon::CheckCircle)
                    ->onColor('success')
                    ->hiddenOn('create'),

                Hidden::make('id')
                    ->hiddenOn('create'),
            ]);
    }
}
